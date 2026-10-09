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

            // A pending order with settlement records is inconsistent. Do not
            // repeat a wallet debit/credit; leave it for reconciliation.
            $paymentKeys = [
                'marketplace:'.$order->reference.':buyer',
                'marketplace:'.$order->reference.':seller',
            ];
            if (WalletMovement::query()->whereIn('operation_key', $paymentKeys)->exists()
                || MarketplaceEarning::query()->where('order_id', $order->id)->exists()) {
                throw new RuntimeException('This order has existing settlement records while still pending. Reconcile it before retrying payment.');
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
            $buyerAfter = self::subtract($buyerBefore, $amount);

            // Escrow-first: deduct from the buyer now, but never credit the
            // seller's available wallet until buyer confirmation and admin release.
            $buyerWallet->forceFill(['available_minor' => $buyerAfter])->save();

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
                'status' => 'escrowed',
            ]);
            DB::table('marketplace_escrows')->insert([
                'order_id' => $order->id,
                'reference' => $order->reference,
                'currency' => $currency,
                'gross_minor' => $amount,
                'seller_net_minor' => $sellerNet,
                'platform_profit_minor' => $fee,
                'status' => 'held',
                'metadata' => json_encode(['escrow_version' => 1, 'created_from' => 'marketplace_order_payment']),
                'created_at' => now(),
                'updated_at' => now(),
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
            if ($order->status === 'refunded') return $order;
            if ($order->status !== 'paid') throw new RuntimeException('Only paid orders can be refunded.');

            $refundKeys = ['marketplace:'.$order->reference.':refund:buyer', 'marketplace:'.$order->reference.':refund:seller'];
            if (WalletMovement::query()->whereIn('operation_key', $refundKeys)->exists()) {
                throw new RuntimeException('Refund movements already exist while the order is paid. Reconcile this order before retrying.');
            }

            $escrow = DB::table('marketplace_escrows')->where('order_id', $order->id)->lockForUpdate()->first();
            if (!$escrow || !in_array($escrow->status, ['held', 'buyer_confirmed'], true)) {
                throw new RuntimeException('Only funds still held in escrow can be refunded through this flow. Released payouts require an admin dispute/recovery process.');
            }
            $earning = MarketplaceEarning::query()->where('order_id', $order->id)->lockForUpdate()->first();
            if (!$earning) throw new RuntimeException('The marketplace earning record is missing; refund was stopped.');

            $currency = strtoupper((string) $order->currency);
            $buyerId = (int) $order->buyer_id;
            $sellerId = (int) $order->seller_id;
            $wallets = WalletAccount::query()->whereIn('user_id', [$buyerId, $sellerId])->where('currency', $currency)->orderBy('user_id')->lockForUpdate()->get()->keyBy('user_id');
            $buyer = $wallets->get($buyerId);
            $seller = $wallets->get($sellerId);
            if (!$buyer || !$seller) throw new RuntimeException('Required refund wallet is unavailable.');

            // Seller has not been paid while escrow is held, so the refund must
            // not debit seller funds. Refund the buyer gross and close the escrow.
            $buyerRefund = (string) $escrow->gross_minor;
            $sellerReversal = '0';
            $buyerBefore = (string) $buyer->available_minor;
            $buyerAfter = self::add($buyerBefore, $buyerRefund);
            $sellerBefore = (string) $seller->available_minor;
            $buyer->forceFill(['available_minor' => $buyerAfter])->save();

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
                'metadata' => ['order_id' => $order->id, 'side' => 'buyer', 'refund_basis' => 'gross_escrow_amount'],
            ]);
            WalletMovement::create([
                'wallet_account_id' => $seller->id,
                'operation_key' => 'marketplace:'.$order->reference.':refund:seller',
                'reference' => $order->reference,
                'type' => 'marketplace_refund',
                'amount_minor' => '0',
                'currency' => $currency,
                'available_before_minor' => $sellerBefore,
                'available_after_minor' => $sellerBefore,
                'held_before_minor' => (string) $seller->held_minor,
                'held_after_minor' => (string) $seller->held_minor,
                'metadata' => ['order_id' => $order->id, 'side' => 'seller', 'seller_was_paid' => false, 'seller_reversal_minor' => $sellerReversal],
            ]);

            DB::table('marketplace_escrows')->where('id', $escrow->id)->update([
                'status' => 'refunded', 'refunded_at' => now(), 'refunded_by' => $actorId, 'updated_at' => now(),
            ]);
            $earning->forceFill([
                'status' => 'refunded',
                'calculation_snapshot' => array_merge((array) $earning->calculation_snapshot, [
                    'refund' => ['refunded_at' => now()->toIso8601String(), 'gross_refunded_to_buyer_minor' => $buyerRefund, 'seller_net_reversed_minor' => '0', 'escrow_refund' => true, 'actor_id' => $actorId, 'admin_initiated' => $isAdmin],
                ]),
            ])->save();
            $product = MarketplaceProduct::query()->lockForUpdate()->find($order->product_id);
            if ($product && $product->isPhysical()) $product->forceFill(['stock_quantity' => self::add((string) $product->stock_quantity, (string) $order->quantity)])->save();
            $order->forceFill(['status' => 'refunded', 'refunded_at' => now()])->save();
            return $order->fresh(['product', 'buyer', 'seller']);
        });
    }

    public function confirmReceipt(MarketplaceOrder $order, int $buyerId): MarketplaceOrder
    {
        return DB::transaction(function () use ($order, $buyerId): MarketplaceOrder {
            $order = MarketplaceOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ((int) $order->buyer_id !== $buyerId) throw new RuntimeException('Only the buyer can confirm receipt.');
            if ($order->status !== 'paid') throw new RuntimeException('Only paid orders can be confirmed.');
            $product = MarketplaceProduct::query()->findOrFail($order->product_id);
            $fulfillment = (string) ($order->fulfillment_status ?? '');
            if ($product->isPhysical() && !in_array($fulfillment, ['submitted', 'shipped', 'delivered', 'completed'], true)) throw new RuntimeException('The physical order must be marked shipped or delivered before receipt confirmation.');
            if ($product->isDigital() && !in_array($fulfillment, ['ready', 'submitted', 'completed'], true)) throw new RuntimeException('The digital item must be marked ready before receipt confirmation.');
            if ($product->product_type === 'service' && !in_array((string) ($order->service_status ?? ''), ['submitted', 'in_review', 'completed'], true)) throw new RuntimeException('The seller must submit the service before you can confirm completion.');
            $escrow = DB::table('marketplace_escrows')->where('order_id', $order->id)->lockForUpdate()->first();
            if (!$escrow) throw new RuntimeException('Escrow record is missing. Contact support; funds were not released.');
            if ($escrow->status === 'buyer_confirmed' || $escrow->status === 'released') return $order;
            if ($escrow->status !== 'held') throw new RuntimeException('This escrow is no longer awaiting buyer confirmation.');
            DB::table('marketplace_escrows')->where('id', $escrow->id)->update([
                'status' => 'buyer_confirmed', 'buyer_confirmed_at' => now(), 'buyer_confirmed_by' => $buyerId, 'updated_at' => now(),
            ]);
            $order->forceFill(['accepted_at' => now()])->save();
            return $order->fresh(['product', 'buyer', 'seller']);
        });
    }

    public function releaseEscrow(MarketplaceOrder $order, int $adminId, ?string $note = null): MarketplaceOrder
    {
        return DB::transaction(function () use ($order, $adminId, $note): MarketplaceOrder {
            $order = MarketplaceOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== 'paid') throw new RuntimeException('Only paid orders can release escrow.');
            $escrow = DB::table('marketplace_escrows')->where('order_id', $order->id)->lockForUpdate()->first();
            if (!$escrow) throw new RuntimeException('Escrow record is missing; no funds were released.');
            if ($escrow->status === 'released') return $order;
            if (!in_array($escrow->status, ['buyer_confirmed', 'held'], true)) throw new RuntimeException('This escrow cannot be released in its current state.');
            // This method is reachable only through the admin-authorised route.
            // Admin may release a held escrow as an explicit override, with an audit note.
            $seller = WalletAccount::query()->where('user_id', (int) $order->seller_id)->where('currency', strtoupper((string) $order->currency))->lockForUpdate()->first();
            if (!$seller) throw new RuntimeException('Seller wallet is unavailable; escrow remains held.');
            $amount = (string) $escrow->seller_net_minor;
            $before = (string) $seller->available_minor;
            $after = self::add($before, $amount);
            $seller->forceFill(['available_minor' => $after])->save();
            WalletMovement::create([
                'wallet_account_id' => $seller->id,
                'operation_key' => 'marketplace:'.$order->reference.':seller',
                'reference' => $order->reference,
                'type' => 'marketplace_sale_release',
                'amount_minor' => $amount,
                'currency' => strtoupper((string) $order->currency),
                'available_before_minor' => $before,
                'available_after_minor' => $after,
                'held_before_minor' => (string) $seller->held_minor,
                'held_after_minor' => (string) $seller->held_minor,
                'metadata' => ['order_id' => $order->id, 'side' => 'seller', 'escrow_release' => true, 'admin_id' => $adminId],
            ]);
            DB::table('marketplace_escrows')->where('id', $escrow->id)->update([
                'status' => 'released', 'released_at' => now(), 'released_by' => $adminId,
                'admin_note' => $note, 'updated_at' => now(),
            ]);
            MarketplaceEarning::query()->where('order_id', $order->id)->update(['status' => 'credited']);
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
            $percentage = self::divideBySmall(self::multiply($amount, (string) $bps), 10000);
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
        return self::divideBySmall(self::multiply($amount, (string) $bps), 10000);
    }

    private static function calculateFee(string $amount): string
    {
        $bps = (int) config('addons.marketplace.commerce.settings.platform_fee_bps', 0);
        return self::calculatePercentageFee($amount, $bps);
    }


    private static function normalizeInteger(string $value): string
    {
        if (!preg_match('/^\\d+$/', $value)) {
            throw new RuntimeException('Financial amounts must be non-negative integer minor units.');
        }
        return ltrim($value, '0') ?: '0';
    }

    private static function multiply(string $a, string $b): string
    {
        $a = self::normalizeInteger($a);
        $b = self::normalizeInteger($b);
        if (function_exists('bcmul')) {
            return self::normalizeInteger(bcmul($a, $b, 0));
        }
        if ($a === '0' || $b === '0') return '0';

        $digitsA = array_map('intval', str_split($a));
        $digitsB = array_map('intval', str_split($b));
        $result = array_fill(0, count($digitsA) + count($digitsB), 0);
        for ($i = count($digitsA) - 1; $i >= 0; $i--) {
            for ($j = count($digitsB) - 1; $j >= 0; $j--) {
                $result[$i + $j + 1] += $digitsA[$i] * $digitsB[$j];
            }
        }
        for ($i = count($result) - 1; $i > 0; $i--) {
            $carry = intdiv($result[$i], 10);
            $result[$i] %= 10;
            $result[$i - 1] += $carry;
        }
        return self::normalizeInteger(implode('', $result));
    }

    private static function compare(string $a, string $b): int
    {
        $a = self::normalizeInteger($a);
        $b = self::normalizeInteger($b);
        if (function_exists('bccomp')) return bccomp($a, $b, 0);
        if (strlen($a) !== strlen($b)) return strlen($a) <=> strlen($b);
        return strcmp($a, $b) <=> 0;
    }

    private static function add(string $a, string $b): string
    {
        $a = self::normalizeInteger($a);
        $b = self::normalizeInteger($b);
        if (function_exists('bcadd')) return self::normalizeInteger(bcadd($a, $b, 0));

        $i = strlen($a) - 1;
        $j = strlen($b) - 1;
        $carry = 0;
        $result = '';
        while ($i >= 0 || $j >= 0 || $carry > 0) {
            $sum = ($i >= 0 ? (int) $a[$i--] : 0)
                + ($j >= 0 ? (int) $b[$j--] : 0) + $carry;
            $result = (string) ($sum % 10).$result;
            $carry = intdiv($sum, 10);
        }
        return self::normalizeInteger($result);
    }

    private static function subtract(string $a, string $b): string
    {
        $a = self::normalizeInteger($a);
        $b = self::normalizeInteger($b);
        if (function_exists('bcsub')) {
            $result = bcsub($a, $b, 0);
            if (self::compare($result, '0') < 0) {
                throw new RuntimeException('Wallet balance cannot become negative.');
            }
            return self::normalizeInteger($result);
        }
        if (self::compare($a, $b) < 0) {
            throw new RuntimeException('Wallet balance cannot become negative.');
        }

        $i = strlen($a) - 1;
        $j = strlen($b) - 1;
        $borrow = 0;
        $result = '';
        while ($i >= 0) {
            $digit = (int) $a[$i--] - $borrow - ($j >= 0 ? (int) $b[$j--] : 0);
            if ($digit < 0) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }
            $result = (string) $digit.$result;
        }
        return self::normalizeInteger($result);
    }

    private static function divideBySmall(string $amount, int $divisor): string
    {
        $amount = self::normalizeInteger($amount);
        if ($divisor < 1) throw new RuntimeException('Financial division requires a positive divisor.');
        $remainder = 0;
        $quotient = '';
        $length = strlen($amount);
        for ($i = 0; $i < $length; $i++) {
            $current = ($remainder * 10) + (int) $amount[$i];
            $quotient .= (string) intdiv($current, $divisor);
            $remainder = $current % $divisor;
        }
        return self::normalizeInteger($quotient);
    }
}
