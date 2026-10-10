<?php

namespace Semizzy\Addons\Savings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\WalletAccount;
use App\Models\WalletMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Semizzy\Addons\Savings\Models\SavingsAccount;
use Semizzy\Addons\Savings\Models\SavingsMovement;
use Semizzy\Addons\Savings\Models\SavingsPlan;
use RuntimeException;

class SavingsController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Savings', [
            'plans' => SavingsPlan::where('active', true)->orderBy('name')->get(),
            'accounts' => SavingsAccount::where('user_id', $request->user()->id)->with('plan')->latest()->get(),
        ]);
    }

    public function apiIndex(Request $request)
    {
        return response()->json([
            'accounts' => SavingsAccount::where('user_id', $request->user()->id)->with('plan')->latest()->get(),
        ]);
    }

    public function create(Request $request)
    {
        $data = $request->validate([
            'plan_key' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:100'],
            'currency' => ['required', 'string', 'size:3'],
            'target_amount_minor' => ['nullable', 'integer', 'min:1'],
        ]);

        $plan = SavingsPlan::where('key', $data['plan_key'])->where('active', true)->firstOrFail();
        $currency = strtoupper($data['currency']);

        if ($currency !== 'NGN') {
            throw new RuntimeException('This savings release currently supports NGN wallet accounts only.');
        }

        if (isset($data['target_amount_minor'])) {
            $target = (int) $data['target_amount_minor'];
            if ($target < (int) $plan->minimum_amount_minor) throw new RuntimeException('The target amount is below the selected plan minimum.');
            if ($plan->maximum_amount_minor !== null && $target > (int) $plan->maximum_amount_minor) throw new RuntimeException('The target amount exceeds the selected plan maximum.');
        }

        $account = SavingsAccount::create([
            'user_id' => $request->user()->id,
            'savings_plan_id' => $plan->id,
            'reference' => 'SAV-'.strtoupper(Str::random(12)),
            'name' => $data['name'],
            'currency' => $currency,
            'target_amount_minor' => $data['target_amount_minor'] ?? null,
            'matures_at' => $plan->lock_days > 0 ? now()->addDays($plan->lock_days) : null,
        ]);

        return response()->json(['account' => $account], 201);
    }

    public function contribute(Request $request, string $reference)
    {
        $data = $request->validate(['amount_minor' => ['required', 'integer', 'min:1']]);
        $idempotency = trim((string) $request->header('Idempotency-Key'));

        if ($idempotency === '') {
            throw new RuntimeException('Idempotency-Key is required for savings contributions.');
        }

        return DB::transaction(function () use ($request, $reference, $data, $idempotency) {
            $account = SavingsAccount::where('reference', $reference)
                ->where('user_id', $request->user()->id)
                ->with('plan')
                ->lockForUpdate()
                ->firstOrFail();

            if ($account->status !== 'active') {
                throw new RuntimeException('Savings account is not active.');
            }
            $plan = $account->plan;
            $amountInt = (int) $data['amount_minor'];
            if ($amountInt < (int) $plan->minimum_amount_minor || ($plan->maximum_amount_minor !== null && $amountInt > (int) $plan->maximum_amount_minor)) {
                throw new RuntimeException('Contribution amount is outside the selected plan limits.');
            }

            $operationKey = 'savings:contribution:'.$reference.':'.$idempotency;
            $existing = SavingsMovement::where('operation_key', $operationKey)->first();
            if ($existing) {
                $this->assertIdempotentReplay($existing, $account, $request->user()->id, 'contribution', (string) $amountInt);
                return response()->json(['account' => $account->fresh(), 'movement' => $existing, 'idempotent' => true]);
            }

            $wallet = WalletAccount::where('user_id', $request->user()->id)
                ->where('currency', $account->currency)
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();

            $amount = (string) $data['amount_minor'];
            $before = (string) $wallet->available_minor;
            if ($this->compare($before, $amount) < 0) {
                throw new RuntimeException('Insufficient wallet balance.');
            }

            $after = $this->subtract($before, $amount);
            $wallet->available_minor = $after;
            $wallet->saveOrFail();

            WalletMovement::create([
                'wallet_account_id' => $wallet->id,
                'operation_key' => $operationKey,
                'reference' => 'SAV-DEBIT-'.strtoupper(Str::random(12)),
                'type' => 'savings_contribution',
                'amount_minor' => $amount,
                'currency' => $wallet->currency,
                'available_before_minor' => $before,
                'available_after_minor' => $after,
                'held_before_minor' => (string) $wallet->held_minor,
                'held_after_minor' => (string) $wallet->held_minor,
                'metadata' => ['savings_reference' => $reference],
            ]);

            $account->balance_minor = $this->add((string) $account->balance_minor, $amount);
            $account->last_contribution_at = now();
            $account->saveOrFail();

            $movement = SavingsMovement::create([
                'savings_account_id' => $account->id,
                'user_id' => $request->user()->id,
                'operation_key' => $operationKey,
                'type' => 'contribution',
                'amount_minor' => $amount,
                'currency' => $account->currency,
                'balance_after_minor' => $account->balance_minor,
                'reference' => 'SVM-'.strtoupper(Str::random(12)),
            ]);

            return response()->json(['account' => $account->fresh(), 'movement' => $movement]);
        });
    }

    public function withdraw(Request $request, string $reference)
    {
        $data = $request->validate(['amount_minor' => ['required', 'integer', 'min:1']]);
        $idempotency = trim((string) $request->header('Idempotency-Key'));

        if ($idempotency === '') {
            throw new RuntimeException('Idempotency-Key is required for savings withdrawals.');
        }

        return DB::transaction(function () use ($request, $reference, $data, $idempotency) {
            $account = SavingsAccount::where('reference', $reference)
                ->where('user_id', $request->user()->id)
                ->with('plan')
                ->lockForUpdate()
                ->firstOrFail();

            if ($account->status !== 'active') {
                throw new RuntimeException('Savings account is not active.');
            }

            $amount = (string) $data['amount_minor'];
            if ($this->compare((string) $account->balance_minor, $amount) < 0) {
                throw new RuntimeException('Insufficient savings balance.');
            }

            if ($account->matures_at && now()->lt($account->matures_at) && !$account->plan->allow_early_withdrawal) {
                throw new RuntimeException('Savings is locked until maturity.');
            }

            $penalty = '0';
            if ($account->matures_at && now()->lt($account->matures_at) && $account->plan->allow_early_withdrawal && (float) $account->plan->early_withdrawal_penalty > 0) {
                $penalty = $this->percentage($amount, (string) $account->plan->early_withdrawal_penalty);
            }

            $operationKey = 'savings:withdrawal:'.$reference.':'.$idempotency;
            $existing = SavingsMovement::where('operation_key', $operationKey)->first();
            if ($existing) {
                $this->assertIdempotentReplay($existing, $account, $request->user()->id, 'withdrawal', $amount);
                return response()->json(['account' => $account->fresh(), 'movement' => $existing, 'idempotent' => true]);
            }

            $wallet = WalletAccount::where('user_id', $request->user()->id)
                ->where('currency', $account->currency)
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();

            $before = (string) $wallet->available_minor;
            $netAmount = $this->subtract($amount, $penalty);
            $after = $this->add($before, $netAmount);
            $wallet->available_minor = $after;
            $wallet->saveOrFail();

            WalletMovement::create([
                'wallet_account_id' => $wallet->id,
                'operation_key' => $operationKey,
                'reference' => 'SAV-CREDIT-'.strtoupper(Str::random(12)),
                'type' => 'savings_withdrawal',
                'amount_minor' => $netAmount,
                'currency' => $wallet->currency,
                'available_before_minor' => $before,
                'available_after_minor' => $after,
                'held_before_minor' => (string) $wallet->held_minor,
                'held_after_minor' => (string) $wallet->held_minor,
                'metadata' => ['savings_reference' => $reference, 'gross_amount_minor' => $amount, 'penalty_minor' => $penalty],
            ]);

            $account->balance_minor = $this->subtract((string) $account->balance_minor, $amount);
            $account->saveOrFail();

            $movement = SavingsMovement::create([
                'savings_account_id' => $account->id,
                'user_id' => $request->user()->id,
                'operation_key' => $operationKey,
                'type' => 'withdrawal',
                'amount_minor' => $amount,
                'currency' => $account->currency,
                'balance_after_minor' => $account->balance_minor,
                'reference' => 'SVM-'.strtoupper(Str::random(12)),
            ]);

            return response()->json(['account' => $account->fresh(), 'movement' => $movement]);
        });
    }

    private function assertIdempotentReplay(SavingsMovement $existing, SavingsAccount $account, int $userId, string $type, string $amount): void
    {
        if (
            (int) $existing->savings_account_id !== (int) $account->id
            || (int) $existing->user_id !== $userId
            || (string) $existing->type !== $type
            || (string) $existing->currency !== (string) $account->currency
            || $this->compare((string) $existing->amount_minor, $amount) !== 0
        ) {
            throw new RuntimeException('This idempotency key has already been used for a different savings operation.');
        }
    }

    private function add(string $a, string $b): string
    {
        if (function_exists('bcadd')) return bcadd($a, $b, 0);
        if (!$this->fitsNativeInteger($a) || !$this->fitsNativeInteger($b)) throw new RuntimeException('Large savings amounts require BCMath.');
        return (string) ((int) $a + (int) $b);
    }

    private function subtract(string $a, string $b): string
    {
        if ($this->compare($a, $b) < 0) throw new RuntimeException('Amount exceeds available balance.');
        if (function_exists('bcsub')) return bcsub($a, $b, 0);
        if (!$this->fitsNativeInteger($a) || !$this->fitsNativeInteger($b)) throw new RuntimeException('Large savings amounts require BCMath.');
        return (string) ((int) $a - (int) $b);
    }

    private function percentage(string $amount, string $rate): string
    {
        if (function_exists('bcmul') && function_exists('bcdiv')) return bcdiv(bcmul($amount, $rate, 8), '100', 0);
        return (string) floor(((float) $amount * (float) $rate) / 100);
    }

    private function compare(string $a, string $b): int
    {
        $a = ltrim($a, '0') ?: '0';
        $b = ltrim($b, '0') ?: '0';
        return strlen($a) <=> strlen($b) ?: strcmp($a, $b);
    }

    private function fitsNativeInteger(string $value): bool
    {
        $value = ltrim($value, '0');
        return PHP_INT_SIZE >= 8 && ctype_digit($value === '' ? '0' : $value) && strlen($value) <= 17;
    }
}
