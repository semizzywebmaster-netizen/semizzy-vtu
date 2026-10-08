<?php

namespace Semizzy\Addons\Marketplace\Services;

use App\Models\WalletAccount;
use App\Models\WalletMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Semizzy\Addons\Marketplace\Models\MarketplaceOrder;
use Semizzy\Addons\Marketplace\Models\MarketplaceProduct;
use Semizzy\Addons\Marketplace\Models\MarketplaceEarning;
use Semizzy\Addons\Marketplace\Models\MarketplaceDigitalDelivery;

final class MarketplaceOrderService
{
    public function create(int $buyerId, array $data): MarketplaceOrder
    {
        return DB::transaction(function () use ($buyerId, $data): MarketplaceOrder {
            $product = MarketplaceProduct::query()->lockForUpdate()->findOrFail((int) $data['product_id']);
            if ($product->status !== 'active') {
                throw new RuntimeException('This product is not available.');
            }
            $quantity = (string) (int) $data['quantity'];
            if ($product->isPhysical() && (int) $product->stock_quantity < (int) $quantity) {
                throw new RuntimeException('Insufficient product stock.');
            }
            if ((int) $product->seller_id === $buyerId) {
                throw new RuntimeException('You cannot purchase your own product.');
            }

            $key = $data['idempotency_key'] ?? null;
            if ($key) {
                $existing = MarketplaceOrder::query()
                    ->where('buyer_id', $buyerId)
                    ->where('idempotency_key', $key)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $total = self::multiply((string) $product->price_minor, $quantity);
                    if ((int) $existing->product_id !== (int) $product->id || (string) $existing->quantity !== $quantity || (string) $existing->total_minor !== $total) {
                        throw new RuntimeException('Idempotency key was already used for a different order.');
                    }
                    return $existing;
                }
            }

            $total = self::multiply((string) $product->price_minor, $quantity);
            return MarketplaceOrder::create([
                'reference' => 'MKT-'.strtoupper(Str::random(18)),
                'buyer_id' => $buyerId,
                'seller_id' => $product->seller_id,
                'product_id' => $product->id,
                'idempotency_key' => $key,
                'quantity' => $quantity,
                'unit_price_minor' => (string) $product->price_minor,
                'total_minor' => $total,
                'currency' => strtoupper((string) $product->currency),
                'status' => 'pending',
                'note' => $data['note'] ?? null,
            ]);
        });
    }

    public function pay(MarketplaceOrder $order, int $buyerId): MarketplaceOrder
    {
        return DB::transaction(function () use ($order, $buyerId): MarketplaceOrder {
            $order = MarketplaceOrder::query()->lockForUpdate()->with('product')->findOrFail($order->id);
            if ((int) $order->buyer_id !== $buyerId) {
                throw new RuntimeException('You are not allowed to pay for this order.');
            }
            if ($order->status === 'paid') {
                return $order;
            }
            if ($order->status !== 'pending') {
                throw new RuntimeException('This order cannot be paid in its current state.');
            }

            $product = MarketplaceProduct::query()->lockForUpdate()->findOrFail($order->product_id);
            if ($product->status !== 'active') {
                throw new RuntimeException('This product is no longer available.');
            }
            if ($product->isPhysical() && (int) $product->stock_quantity < (int) $order->quantity) {
                throw new RuntimeException('Insufficient product stock.');
            }

            $currency = strtoupper((string) $order->currency);
            if ($currency !== 'NGN') {
                throw new RuntimeException('Only NGN marketplace settlement is currently supported.');
            }

            $wallets = WalletAccount::query()->whereIn('user_id', [$buyerId, (int) $order->seller_id])->where('currency', $currency)->orderBy('user_id')->lockForUpdate()->get()->keyBy('user_id');
            $buyerWallet = $wallets->get($buyerId);
            $sellerWallet = $wallets->get((int) $order->seller_id);
            if (!$buyerWallet || !$sellerWallet) {
                throw new RuntimeException('Required marketplace wallet is unavailable.');
            }

            $amount = (string) $order->total_minor;
            $fee = self::calculateFee($amount);
            $sellerNet = self::subtract($amount, $fee);
            if (self::compare($buyerWallet->available_minor, $amount) < 0) {
                throw new RuntimeException('Insufficient wallet balance.');
            }

            $buyerBefore = (string) $buyerWallet->available_minor;
            $sellerBefore = (string) $sellerWallet->available_minor;
            $buyerAfter = self::subtract($buyerBefore, $amount);
            $sellerAfter = self::add($sellerBefore, $sellerNet);

            $buyerWallet->forceFill(['available_minor' => $buyerAfter])->save();
            $sellerWallet->forceFill(['available_minor' => $sellerAfter])->save();

            if ($product->isPhysical()) {
                $product->forceFill([
                    'stock_quantity' => self::subtract((string) $product->stock_quantity, (string) $order->quantity),
                ])->save();
            }

            $fulfillment = match ($product->product_type) {
                'digital' => ['fulfillment_status' => 'ready', 'delivery_status' => 'available'],
                'service' => ['fulfillment_status' => 'in_progress', 'service_status' => 'in_progress'],
                default => ['fulfillment_status' => 'unfulfilled', 'delivery_status' => 'pending'],
            };

            WalletMovement::create([
                'wallet_account_id' => $buyerWallet->id,
                'operation_key' => 'marketplace:'.$order->reference.':buyer',
                'reference' => $order->reference,
                'type' => 'marketplace_purchase',
                'amount_minor' => $amount,
                'currency' => $currency,
                'available_before_minor' => $buyerBefore,
                'available_after_minor' => $buyerAfter,
                'held_before_minor' => (string) $buyerWallet->held_minor,
                'held_after_minor' => (string) $buyerWallet->held_minor,
                'metadata' => ['order_id' => $order->id, 'side' => 'buyer'],
            ]);

            WalletMovement::create([
                'wallet_account_id' => $sellerWallet->id,
                'operation_key' => 'marketplace:'.$order->reference.':seller',
                'reference' => $order->reference,
                'type' => 'marketplace_sale',
                'amount_minor' => $sellerNet,
                'currency' => $currency,
                'available_before_minor' => $sellerBefore,
                'available_after_minor' => $sellerAfter,
                'held_before_minor' => (string) $sellerWallet->held_minor,
                'held_after_minor' => (string) $sellerWallet->held_minor,
                'metadata' => ['order_id' => $order->id, 'side' => 'seller'],
            ]);

            MarketplaceEarning::create(['order_id'=>$order->id,'seller_id'=>$order->seller_id,'gross_minor'=>$amount,'fee_minor'=>$fee,'net_minor'=>$sellerNet,'currency'=>$currency,'status'=>'credited']);
            $order->forceFill(array_merge(['status' => 'paid', 'paid_at' => now()], $fulfillment))->save();

            if ($product->isDigital()) {
                $assets = $product->digitalAssets()->get();
                foreach ($assets as $asset) {
                    MarketplaceDigitalDelivery::query()->firstOrCreate(
                        ['order_id' => $order->id, 'asset_id' => $asset->id],
                        [
                            'delivery_token' => Str::random(64),
                            'download_limit' => $product->download_limit,
                        ]
                    );
                }
            }

            return $order->fresh(['product', 'buyer', 'seller']);
        });
    }

    public function cancel(MarketplaceOrder $order, int $buyerId): MarketplaceOrder
    {
        return DB::transaction(function () use ($order, $buyerId): MarketplaceOrder {
            $order = MarketplaceOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ((int)$order->buyer_id !== $buyerId) throw new RuntimeException('You are not allowed to cancel this order.');
            if ($order->status === 'cancelled') return $order;
            if ($order->status !== 'pending') throw new RuntimeException('Only pending orders can be cancelled.');
            $order->forceFill(['status'=>'cancelled','cancelled_at'=>now()])->save();
            return $order->fresh(['product','buyer','seller']);
        });
    }

    public function refund(MarketplaceOrder $order, int $actorId, bool $isAdmin = false): MarketplaceOrder
    {
        return DB::transaction(function () use ($order, $actorId, $isAdmin): MarketplaceOrder {
            $order = MarketplaceOrder::query()->lockForUpdate()->findOrFail($order->id);
            if (!$isAdmin && (int)$order->buyer_id !== $actorId) throw new RuntimeException('You are not allowed to refund this order.');
            if ($order->status === 'refunded') return $order;
            if ($order->status !== 'paid') throw new RuntimeException('Only paid orders can be refunded.');
            $currency = strtoupper((string)$order->currency);
            $wallets = WalletAccount::query()->whereIn('user_id', [(int)$order->buyer_id,(int)$order->seller_id])->where('currency',$currency)->orderBy('user_id')->lockForUpdate()->get()->keyBy('user_id');
            $buyer = $wallets->get((int)$order->buyer_id);
            $seller = $wallets->get((int)$order->seller_id);
            if (!$buyer || !$seller) throw new RuntimeException('Required refund wallet is unavailable.');
            $amount=(string)$order->total_minor;
            if (self::compare($seller->available_minor,$amount)<0) throw new RuntimeException('Seller balance is insufficient for this refund.');
            $buyerBefore=(string)$buyer->available_minor; $sellerBefore=(string)$seller->available_minor;
            $buyerAfter=self::add($buyerBefore,$amount); $sellerAfter=self::subtract($sellerBefore,$amount);
            $buyer->forceFill(['available_minor'=>$buyerAfter])->save();
            $seller->forceFill(['available_minor'=>$sellerAfter])->save();
            WalletMovement::create(['wallet_account_id'=>$buyer->id,'operation_key'=>'marketplace:'.$order->reference.':refund:buyer','reference'=>$order->reference,'type'=>'marketplace_refund','amount_minor'=>$amount,'currency'=>$currency,'available_before_minor'=>$buyerBefore,'available_after_minor'=>$buyerAfter,'held_before_minor'=>(string)$buyer->held_minor,'held_after_minor'=>(string)$buyer->held_minor,'metadata'=>['order_id'=>$order->id,'side'=>'buyer']]);
            WalletMovement::create(['wallet_account_id'=>$seller->id,'operation_key'=>'marketplace:'.$order->reference.':refund:seller','reference'=>$order->reference,'type'=>'marketplace_refund','amount_minor'=>$amount,'currency'=>$currency,'available_before_minor'=>$sellerBefore,'available_after_minor'=>$sellerAfter,'held_before_minor'=>(string)$seller->held_minor,'held_after_minor'=>(string)$seller->held_minor,'metadata'=>['order_id'=>$order->id,'side'=>'seller']]);
            $product=MarketplaceProduct::query()->lockForUpdate()->find($order->product_id);
            if($product && $product->isPhysical()){$product->forceFill(['stock_quantity'=>self::add((string)$product->stock_quantity,(string)$order->quantity)])->save();}
            MarketplaceEarning::query()->where('order_id',$order->id)->update(['status'=>'refunded']);
            $order->forceFill(['status'=>'refunded','refunded_at'=>now()])->save();
            return $order->fresh(['product','buyer','seller']);
        });
    }

    private static function calculateFee(string $amount): string
    {
        $bps = (int) config('addons.marketplace.commerce.settings.platform_fee_bps', 0);
        if ($bps <= 0) return '0';
        if (function_exists('bcmul')) return bcdiv(bcmul($amount, (string)$bps, 0), '10000', 0);
        return (string) intdiv((int)$amount * $bps, 10000);
    }

    private static function multiply(string $a, string $b): string
    {
        if (function_exists('bcmul')) {
            return bcmul($a, $b, 0);
        }
        return (string) ((int) $a * (int) $b);
    }

    private static function compare(string $a, string $b): int
    {
        if (function_exists('bccomp')) {
            return bccomp($a, $b, 0);
        }
        return (int) $a <=> (int) $b;
    }

    private static function add(string $a, string $b): string
    {
        if (function_exists('bcadd')) {
            return bcadd($a, $b, 0);
        }
        return (string) ((int) $a + (int) $b);
    }

    private static function subtract(string $a, string $b): string
    {
        if (function_exists('bcsub')) {
            return bcsub($a, $b, 0);
        }
        $result = (int) $a - (int) $b;
        if ($result < 0) {
            throw new RuntimeException('Wallet balance cannot become negative.');
        }
        return (string) $result;
    }
}
