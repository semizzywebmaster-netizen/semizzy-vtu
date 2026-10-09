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
            if ($product->isDigital() && !$product->digitalAssets()->exists()) {
                throw new RuntimeException('This digital product is not ready for delivery yet.');
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
            $category = $product->category()->first();
            $profitBps = $category ? (int) $category->sale_profit_bps : 0;
            $profitFixed = $category ? (string) ($category->sale_profit_fixed_minor ?? '0') : '0';
            $usedFallbackFee = $profitBps <= 0 && self::compare($profitFixed, '0') <= 0;
            if ($usedFallbackFee) {
                $profitBps = (int) config('addons.marketplace.commerce.settings.platform_fee_bps', 0);
                $profitFixed = '0';
            }
            $fee = self::calculateCategoryFee($product, $amount);
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

            MarketplaceEarning::create([
                'order_id' => $order->id,
                'seller_id' => $order->seller_id,
                'category_id' => $category?->id,
                'gross_minor' => $amount,
                'fee_minor' => $fee,
                'net_minor' => $sellerNet,
                'gross_amount_minor' => $amount,
                'category_profit_bps' => $profitBps,
                'category_profit_fixed_minor' => $profitFixed,
                'platform_profit_minor' => $fee,
                'seller_net_minor' => $sellerNet,
                'calculation_snapshot' => [
                    'version' => 1,
                    'calculated_at' => now()->toIso8601String(),
                    'category_id' => $category?->id,
                    'category_name' => $category?->name,
                    'category_slug' => $category?->slug,
                    'configured_percentage_bps' => $profitBps,
                    'configured_fixed_minor' => $profitFixed,
                    'fallback_platform_fee_used' => $usedFallbackFee,
                    'gross_amount_minor' => $amount,
                    'percentage_profit_minor' => self::calculatePercentageFee($amount, $profitBps),
                    'fixed_profit_minor' => $profitFixed,
                    'platform_profit_minor' => $fee,
                    'seller_net_minor' => $sellerNet,
                    'currency' => $currency,
                    'quantity' => (string) $order->quantity,
                    'unit_price_minor' => (string) $order->unit_price_minor,
                ],
                'currency' => $currency,
                'status' => 'credited',
            ]);
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
            if (!$isAdmin && (int) $order->buyer_id !== $actorId) {
                throw new RuntimeException('You are not allowed to refund this order.');
            }
            if ($order->status === 'refunded') {
                return $order;
            }
            if ($order->status !== 'paid') {
                throw new RuntimeException('Only paid orders can be refunded.');
            }

            $earning = MarketplaceEarning::query()->where('order_id', $order->id)->lockForUpdate()->first();
            if (!$earning) {
                throw new RuntimeException('The marketplace earning record is missing; refund was stopped to prevent an unbalanced settlement.');
            }
            if ($earning->status === 'refunded') {
                throw new RuntimeException('The earning is already marked refunded while the order is still paid. Reconcile this order before retrying.');
            }

            $currency = strtoupper((string) $order->currency);
            $buyerId = (int) $order->buyer_id;
            $sellerId = (int) $order->seller_id;
            $wallets = WalletAccount::query()
                ->whereIn('user_id', [$buyerId, $sellerId])
                ->where('currency', $currency)
                ->orderBy('user_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');
            $buyer = $wallets->get($buyerId);
            $seller = $wallets->get($sellerId);
            if (!$buyer || !$seller) {
                throw new RuntimeException('Required refund wallet is unavailable.');
            }

            // Refund the buyer's gross payment, but reverse only the seller net
            // actually credited at settlement. The platform fee is not taken
            // from the seller a second time.
            $buyerRefund = (string) $order->total_minor;
            $sellerReversal = (string) ($earning->seller_net_minor ?? $earning->net_minor ?? '0');
            if (self::compare($sellerReversal, '0') < 0) {
                throw new RuntimeException('The recorded seller settlement is invalid; refund was stopped.');
            }
            if (self::compare((string) $seller->available_minor, $sellerReversal) < 0) {
                throw new RuntimeException('Seller available balance is insufficient to reverse the original seller net payout. No refund was applied.');
            }

            $buyerBefore = (string) $buyer->available_minor;
            $sellerBefore = (string) $seller->available_minor;
            $buyerAfter = self::add($buyerBefore, $buyerRefund);
            $sellerAfter = self::subtract($sellerBefore, $sellerReversal);

            $buyer->forceFill(['available_minor' => $buyerAfter])->save();
            $seller->forceFill(['available_minor' => $sellerAfter])->save();

            WalletMovement::create([
                'wallet_account_id' => $buyer->id,
                'operation_key' => 'marketplace:'.$order->reference.':refund:buyer',
                'reference' => $order->reference,
                'type' => 'marketplace_refund',
                'amount_minor' => $buyerRefund,
                'currency' => $currency,
                'available_before_minor' => $buyerBefore,
                'available_after_minor' => $buyerAfter,
                'held_before_minor' => (string) $buyer->held_minor,
                'held_after_minor' => (string) $buyer->held_minor,
                'metadata' => [
                    'order_id' => $order->id,
                    'side' => 'buyer',
                    'refund_basis' => 'gross_paid_amount',
                    'seller_reversal_minor' => $sellerReversal,
                    'platform_profit_retained_minor' => (string) ($earning->platform_profit_minor ?? $earning->fee_minor ?? '0'),
                ],
            ]);
            WalletMovement::create([
                'wallet_account_id' => $seller->id,
                'operation_key' => 'marketplace:'.$order->reference.':refund:seller',
                'reference' => $order->reference,
                'type' => 'marketplace_refund',
                'amount_minor' => $sellerReversal,
                'currency' => $currency,
                'available_before_minor' => $sellerBefore,
                'available_after_minor' => $sellerAfter,
                'held_before_minor' => (string) $seller->held_minor,
                'held_after_minor' => (string) $seller->held_minor,
                'metadata' => [
                    'order_id' => $order->id,
                    'side' => 'seller',
                    'refund_basis' => 'original_seller_net_payout',
                    'gross_refunded_to_buyer_minor' => $buyerRefund,
                    'platform_profit_minor' => (string) ($earning->platform_profit_minor ?? $earning->fee_minor ?? '0'),
                ],
            ]);

            $product = MarketplaceProduct::query()->lockForUpdate()->find($order->product_id);
            if ($product && $product->isPhysical()) {
                $product->forceFill([
                    'stock_quantity' => self::add((string) $product->stock_quantity, (string) $order->quantity),
                ])->save();
            }

            $earning->forceFill([
                'status' => 'refunded',
                'calculation_snapshot' => array_merge((array) $earning->calculation_snapshot, [
                    'refund' => [
                        'refunded_at' => now()->toIso8601String(),
                        'gross_refunded_to_buyer_minor' => $buyerRefund,
                        'seller_net_reversed_minor' => $sellerReversal,
                        'platform_profit_minor' => (string) ($earning->platform_profit_minor ?? $earning->fee_minor ?? '0'),
                        'actor_id' => $actorId,
                        'admin_initiated' => $isAdmin,
                    ],
                ]),
            ])->save();

            $order->forceFill(['status' => 'refunded', 'refunded_at' => now()])->save();
            return $order->fresh(['product', 'buyer', 'seller']);
        });
    }

    private static function calculateCategoryFee(MarketplaceProduct $product, string $amount): string
    {
        $category = $product->category()->first();
        $bps = $category ? (int) $category->sale_profit_bps : 0;
        $fixed = $category ? (string) ($category->sale_profit_fixed_minor ?? '0') : '0';
        if ($bps <= 0 && self::compare($fixed, '0') <= 0) {
            $bps = (int) config('addons.marketplace.commerce.settings.platform_fee_bps', 0);
            $fixed = '0';
        }
        $percentage = '0';
        if ($bps > 0) {
            $percentage = function_exists('bcmul')
                ? bcdiv(bcmul($amount, (string) $bps, 0), '10000', 0)
                : (string) intdiv((int) $amount * $bps, 10000);
        }
        $total = self::add($percentage, $fixed);
        if (self::compare($total, $amount) > 0) {
            throw new RuntimeException('Category sales profit cannot exceed the sale amount.');
        }
        return $total;
    }

    private static function calculatePercentageFee(string $amount, int $bps): string
    {
        if ($bps <= 0) return '0';
        if (function_exists('bcmul')) return bcdiv(bcmul($amount, (string) $bps, 0), '10000', 0);
        return (string) intdiv((int) $amount * $bps, 10000);
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
