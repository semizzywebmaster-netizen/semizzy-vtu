<?php

namespace Semizzy\Addons\Investments\Services;

use App\Models\WalletAccount;
use App\Models\WalletMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Semizzy\Addons\Investments\Models\InvestmentAccount;
use Semizzy\Addons\Investments\Models\InvestmentMovement;
use Semizzy\Addons\Investments\Models\InvestmentProduct;

class InvestmentService
{
    private function add(string $a, string $b): string
    {
        return function_exists('bcadd') ? bcadd($a, $b, 0) : (string) ((int) $a + (int) $b);
    }

    private function sub(string $a, string $b): string
    {
        return function_exists('bcsub') ? bcsub($a, $b, 0) : (string) ((int) $a - (int) $b);
    }

    private function cmp(string $a, string $b): int
    {
        return function_exists('bccomp') ? bccomp($a, $b, 0) : ((int) $a <=> (int) $b);
    }

    private function pct(string $amount, string $rate): string
    {
        if ($this->cmp($amount, '0') <= 0 || (float) $rate <= 0) {
            return '0';
        }

        if (function_exists('bcmul')) {
            return bcdiv(bcmul($amount, $rate, 4), '100', 0);
        }

        return (string) floor((float) $amount * (float) $rate / 100);
    }

    public function create(int $userId, array $data): InvestmentAccount
    {
        $product = InvestmentProduct::where('key', $data['product_key'])
            ->where('active', true)
            ->firstOrFail();

        $amount = (string) $data['amount_minor'];

        if (
            ! ctype_digit($amount)
            || $this->cmp($amount, (string) $product->minimum_amount_minor) < 0
            || ($product->maximum_amount_minor !== null
                && $this->cmp($amount, (string) $product->maximum_amount_minor) > 0)
        ) {
            throw new RuntimeException('Investment amount is outside product limits.');
        }

        return InvestmentAccount::create([
            'user_id' => $userId,
            'investment_product_id' => $product->id,
            'reference' => 'INV-' . strtoupper(Str::random(12)),
            'currency' => $product->currency,
            'principal_minor' => $amount,
            'status' => 'pending',
        ]);
    }

    public function fund(InvestmentAccount $account, int $userId, string $idempotency): InvestmentAccount
    {
        if ($idempotency === '') {
            throw new RuntimeException('A valid idempotency key is required.');
        }

        return DB::transaction(function () use ($account, $userId, $idempotency) {
            $investment = InvestmentAccount::whereKey($account->id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->with('product')
                ->firstOrFail();

            if ($investment->status !== 'pending') {
                throw new RuntimeException('Investment is not awaiting funding.');
            }

            $operationKey = 'investment:fund:' . $investment->reference . ':' . $idempotency;

            if (InvestmentMovement::where('operation_key', $operationKey)->exists()) {
                return $investment->fresh('product');
            }

            $wallet = WalletAccount::where('user_id', $userId)
                ->where('currency', $investment->currency)
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();

            $before = (string) $wallet->available_minor;

            if ($this->cmp($before, (string) $investment->principal_minor) < 0) {
                throw new RuntimeException('Insufficient wallet balance.');
            }

            $after = $this->sub($before, (string) $investment->principal_minor);
            $wallet->available_minor = $after;
            $wallet->saveOrFail();

            WalletMovement::create([
                'wallet_account_id' => $wallet->id,
                'operation_key' => $operationKey,
                'reference' => 'INV-FUND-' . strtoupper(Str::random(10)),
                'type' => 'investment_funding',
                'amount_minor' => (string) $investment->principal_minor,
                'currency' => $wallet->currency,
                'available_before_minor' => $before,
                'available_after_minor' => $after,
                'held_before_minor' => (string) $wallet->held_minor,
                'held_after_minor' => (string) $wallet->held_minor,
                'metadata' => ['investment_reference' => $investment->reference],
            ]);

            $investment->status = 'active';
            $investment->funded_at = now();
            $investment->matures_at = now()->addDays((int) $investment->product->term_days);
            $investment->saveOrFail();

            InvestmentMovement::create([
                'investment_account_id' => $investment->id,
                'user_id' => $userId,
                'operation_key' => $operationKey,
                'reference' => 'IMV-FUND-' . strtoupper(Str::random(10)),
                'type' => 'funding',
                'amount_minor' => $investment->principal_minor,
                'currency' => $investment->currency,
                'balance_after_minor' => $investment->principal_minor,
            ]);

            return $investment->fresh('product');
        });
    }

    public function processMaturity(int $limit = 100): int
    {
        $limit = max(1, min($limit, 500));
        $processed = 0;

        InvestmentAccount::where('status', 'active')
            ->whereNotNull('matures_at')
            ->where('matures_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->each(function ($id) use (&$processed) {
                $done = DB::transaction(function () use ($id) {
                    $investment = InvestmentAccount::whereKey($id)
                        ->lockForUpdate()
                        ->with('product')
                        ->first();

                    if (! $investment || $investment->status !== 'active') {
                        return false;
                    }

                    $profit = $this->pct(
                        (string) $investment->principal_minor,
                        (string) $investment->product->profit_rate
                    );

                    $investment->profit_minor = $profit;
                    $investment->status = 'matured';
                    $investment->saveOrFail();

                    InvestmentMovement::firstOrCreate(
                        ['operation_key' => 'investment:profit:' . $investment->reference],
                        [
                            'investment_account_id' => $investment->id,
                            'user_id' => $investment->user_id,
                            'reference' => 'IMV-PROFIT-' . strtoupper(Str::random(10)),
                            'type' => 'profit',
                            'amount_minor' => $profit,
                            'currency' => $investment->currency,
                            'balance_after_minor' => $this->add(
                                (string) $investment->principal_minor,
                                $profit
                            ),
                        ]
                    );

                    return true;
                });

                if ($done) {
                    $processed++;
                }
            });

        return $processed;
    }

    public function redeem(InvestmentAccount $account, int $userId, string $idempotency): InvestmentAccount
    {
        if ($idempotency === '') {
            throw new RuntimeException('A valid idempotency key is required.');
        }

        return DB::transaction(function () use ($account, $userId, $idempotency) {
            $investment = InvestmentAccount::whereKey($account->id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->with('product')
                ->firstOrFail();

            if (! in_array($investment->status, ['active', 'matured'], true)) {
                throw new RuntimeException('Investment cannot be redeemed.');
            }

            if (
                $investment->status === 'active'
                && ! $investment->product->allow_early_redemption
            ) {
                throw new RuntimeException('Investment is locked until maturity.');
            }

            $operationKey = 'investment:redeem:' . $investment->reference . ':' . $idempotency;

            if (InvestmentMovement::where('operation_key', $operationKey)->exists()) {
                return $investment->fresh('product');
            }

            $gross = $this->add(
                (string) $investment->principal_minor,
                (string) $investment->profit_minor
            );

            $penalty = $investment->status === 'active'
                ? $this->pct($gross, (string) $investment->product->early_redemption_penalty)
                : '0';

            $net = $this->sub($gross, $penalty);

            $wallet = WalletAccount::where('user_id', $userId)
                ->where('currency', $investment->currency)
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();

            $before = (string) $wallet->available_minor;
            $after = $this->add($before, $net);
            $wallet->available_minor = $after;
            $wallet->saveOrFail();

            WalletMovement::create([
                'wallet_account_id' => $wallet->id,
                'operation_key' => $operationKey,
                'reference' => 'INV-RED-' . strtoupper(Str::random(10)),
                'type' => 'investment_redemption',
                'amount_minor' => $net,
                'currency' => $wallet->currency,
                'available_before_minor' => $before,
                'available_after_minor' => $after,
                'held_before_minor' => (string) $wallet->held_minor,
                'held_after_minor' => (string) $wallet->held_minor,
                'metadata' => [
                    'investment_reference' => $investment->reference,
                    'gross_amount_minor' => $gross,
                    'penalty_minor' => $penalty,
                ],
            ]);

            $investment->redeemed_minor = $net;
            $investment->status = 'redeemed';
            $investment->redeemed_at = now();
            $investment->saveOrFail();

            InvestmentMovement::create([
                'investment_account_id' => $investment->id,
                'user_id' => $userId,
                'operation_key' => $operationKey . ':ledger',
                'reference' => 'IMV-RED-' . strtoupper(Str::random(10)),
                'type' => 'redemption',
                'amount_minor' => $net,
                'currency' => $investment->currency,
                'balance_after_minor' => '0',
            ]);

            return $investment->fresh('product');
        });
    }
}
