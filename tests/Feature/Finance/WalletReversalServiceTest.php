<?php

namespace Tests\Feature\Finance;

use App\Models\FinancialOperation;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletMovement;
use App\Services\Finance\WalletReversalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class WalletReversalServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reverses_debit_once_and_marks_matching_completed_operation_reversed(): void
    {
        $user = User::factory()->create();
        $wallet = WalletAccount::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'available_minor' => '800',
            'held_minor' => '0',
            'status' => 'active',
        ]);
        $operation = FinancialOperation::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'REVERSAL-TEST-'.Str::random(8),
            'user_id' => $user->id,
            'type' => 'p2p.transfer',
            'status' => 'completed',
            'amount_minor' => '200',
            'currency' => 'NGN',
            'idempotency_key' => 'original-op-'.Str::random(8),
        ]);
        $original = $this->movement($wallet, 'original:debit', $operation->reference, 'debit', '200', '1000', '800');

        $service = app(WalletReversalService::class);
        $first = $service->reverse($original, 'Approved support reversal');
        $second = $service->reverse($original, 'Retry of same reversal');

        $this->assertSame($first->id, $second->id);
        $this->assertSame('1000', (string) $wallet->fresh()->available_minor);
        $this->assertSame('wallet_reversal', $first->type);
        $this->assertSame((string) $original->id, (string) $first->metadata['reversal_of_movement_id']);
        $this->assertSame('reversed', $operation->fresh()->status);
        $this->assertDatabaseCount('wallet_movements', 2);
    }

    public function test_refuses_to_reverse_credit_when_wallet_has_already_spent_the_funds(): void
    {
        $user = User::factory()->create();
        $wallet = WalletAccount::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'available_minor' => '100',
            'held_minor' => '0',
            'status' => 'active',
        ]);
        $original = $this->movement($wallet, 'original:credit', 'PAY-REVERSAL-TEST', 'payment_funding', '500', '0', '500');

        try {
            app(WalletReversalService::class)->reverse($original, 'Refund approved');
            $this->fail('Reversing a credit must not overdraw the wallet.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Insufficient available wallet balance to reverse the original credit.', $exception->getMessage());
        }

        $this->assertSame('100', (string) $wallet->fresh()->available_minor);
        $this->assertDatabaseCount('wallet_movements', 1);
    }

    public function test_rejects_blank_reason(): void
    {
        $user = User::factory()->create();
        $wallet = WalletAccount::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'available_minor' => '900',
            'held_minor' => '0',
            'status' => 'active',
        ]);
        $original = $this->movement($wallet, 'original:debit', 'DEBIT-REVERSAL-TEST', 'debit', '100', '1000', '900');
        $service = app(WalletReversalService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A reason is required to reverse a wallet movement.');
        $service->reverse($original, '   ');
    }

    public function test_a_reversal_movement_cannot_be_reversed_again(): void
    {
        $user = User::factory()->create();
        $wallet = WalletAccount::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'available_minor' => '900',
            'held_minor' => '0',
            'status' => 'active',
        ]);
        $original = $this->movement($wallet, 'original:debit', 'DEBIT-DOUBLE-REVERSAL', 'debit', '100', '1000', '900');
        $service = app(WalletReversalService::class);
        $reversal = $service->reverse($original, 'First approved reversal');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A reversal movement cannot itself be reversed.');
        $service->reverse($reversal, 'Attempt to reverse the reversal');
    }

    private function movement(WalletAccount $wallet, string $key, string $reference, string $type, string $amount, string $before, string $after): WalletMovement
    {
        return WalletMovement::create([
            'wallet_account_id' => $wallet->id,
            'operation_key' => $key,
            'reference' => $reference,
            'type' => $type,
            'amount_minor' => $amount,
            'currency' => 'NGN',
            'available_before_minor' => $before,
            'available_after_minor' => $after,
            'held_before_minor' => '0',
            'held_after_minor' => '0',
            'metadata' => [],
        ]);
    }
}
