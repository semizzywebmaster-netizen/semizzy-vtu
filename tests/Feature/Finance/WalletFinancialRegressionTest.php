<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletMovement;
use App\Services\Finance\AdminWalletDebitService;
use App\Services\Finance\WalletCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WalletFinancialRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_replayed_credit_operation_does_not_credit_wallet_twice(): void
    {
        $user = User::factory()->create();
        $wallet = $this->wallet($user, '1000');
        $service = app(WalletCreditService::class);

        $service->credit($user, '10.00', 'test:credit:one', 'TEST-CREDIT-1', 'payment_funding');
        $service->credit($user, '10.00', 'test:credit:one', 'TEST-CREDIT-1', 'payment_funding');

        $this->assertSame('2000', $wallet->fresh()->available_minor);
        $this->assertDatabaseCount('wallet_movements', 1);
    }

    public function test_admin_debit_cannot_overdraw_wallet_and_rolls_back_cleanly(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $wallet = $this->wallet($user, '500');

        try {
            app(AdminWalletDebitService::class)->debit($user, '6.00', $admin, 'regression test');
            $this->fail('Debit larger than available balance must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Insufficient available wallet balance for this debit.', $exception->getMessage());
        }

        $this->assertSame('500', $wallet->fresh()->available_minor);
        $this->assertDatabaseCount('wallet_movements', 0);
    }

    public function test_successful_admin_debit_records_before_and_after_balances(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $wallet = $this->wallet($user, '10000');

        app(AdminWalletDebitService::class)->debit($user, '25.50', $admin, 'approved adjustment');

        $this->assertSame('7450', $wallet->fresh()->available_minor);
        $movement = WalletMovement::query()->where('wallet_account_id', $wallet->id)->firstOrFail();
        // SQLite and MySQL may hydrate integer-affinity columns differently; compare
        // numeric values while preserving the minor-unit assertions.
        $this->assertEquals(2550, $movement->amount_minor);
        $this->assertEquals(10000, $movement->available_before_minor);
        $this->assertEquals(7450, $movement->available_after_minor);
        $this->assertSame('admin_debit', $movement->type);
        $this->assertSame('approved adjustment', $movement->metadata['note']);
    }

    public function test_admin_debit_idempotency_key_prevents_duplicate_debits_and_rejects_changed_amount(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $wallet = $this->wallet($user, '10000');
        $service = app(AdminWalletDebitService::class);

        $first = $service->debit($user, '25.00', $admin, 'approved adjustment', 'admin-debit-request-1');
        $retry = $service->debit($user, '25.00', $admin, 'approved adjustment', 'admin-debit-request-1');

        $this->assertSame('7500', (string) $wallet->fresh()->available_minor);
        $this->assertDatabaseCount('wallet_movements', 1);
        $this->assertSame((string) $first->available_minor, (string) $retry->available_minor);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This idempotency key has already been used for a different wallet debit.');
        $service->debit($user, '30.00', $admin, 'changed amount', 'admin-debit-request-1');
    }

    private function wallet(User $user, string $availableMinor): WalletAccount
    {
        return WalletAccount::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'available_minor' => $availableMinor,
            'held_minor' => '0',
            'status' => 'active',
        ]);
    }
}
