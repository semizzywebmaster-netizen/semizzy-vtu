<?php

namespace Semizzy\Addons\Marketplace\Services;

use App\Models\WalletAccount;
use App\Models\WalletMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Semizzy\Addons\Marketplace\Models\MarketplaceOrder;
use Semizzy\Addons\Marketplace\Models\MarketplaceProduct;

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
            if ((int) $product->stock_quantity < (int) $quantity) {
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
            if ((int) $product->stock_quantity < (int) $order->quantity) {
                throw new RuntimeException('Insufficient product stock.');
            }

            $currency = strtoupper((string) $order->currency);
            if ($currency !== 'NGN') {
                throw new RuntimeException('Only NGN marketplace settlement is currently supported.');
            }

            $buyerWallet = WalletAccount::query()->where('user_id', $buyerId)->where('currency', $currency)->lockForUpdate()->first();
            $sellerWallet = WalletAccount::query()->where('user_id', $order->seller_id)->where('currency', $currency)->lockForUpdate()->first();
            if (!$buyerWallet || !$sellerWallet) {
                throw new RuntimeException('Required marketplace wallet is unavailable.');
            }

            $amount = (string) $order->total_minor;
            if (self::compare($buyerWallet->available_minor, $amount) < 0) {
                throw new RuntimeException('Insufficient wallet balance.');
            }

            $buyerBefore = (string) $buyerWallet->available_minor;
            $sellerBefore = (string) $sellerWallet->available_minor;
            $buyerAfter = self::subtract($buyerBefore, $amount);
            $sellerAfter = self::add($sellerBefore, $amount);

            $buyerWallet->forceFill(['available_minor' => $buyerAfter])->save();
            $sellerWallet->forceFill(['available_minor' => $sellerAfter])->save();

            $product->forceFill([
                'stock_quantity' => self::subtract((string) $product->stock_quantity, (string) $order->quantity),
            ])->save();

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
                'amount_minor' => $amount,
                'currency' => $currency,
                'available_before_minor' => $sellerBefore,
                'available_after_minor' => $sellerAfter,
                'held_before_minor' => (string) $sellerWallet->held_minor,
                'held_after_minor' => (string) $sellerWallet->held_minor,
                'metadata' => ['order_id' => $order->id, 'side' => 'seller'],
            ]);

            $order->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
            ])->save();

            return $order->fresh(['product', 'buyer', 'seller']);
        });
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
