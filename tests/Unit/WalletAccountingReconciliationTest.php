<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletMovement;
use App\Services\Finance\WalletAccountingReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WalletAccountingReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_reconciliation_accepts_a_contiguous_movement_chain(): void
    {
        $wallet = WalletAccount::create([
            'user_id' => User::factory()->create()->id,
            'currency' => 'NGN',
            'available_minor' => '9000',
            'held_minor' => '1000',
            'status' => 'active',
        ]);

        WalletMovement::create([
            'wallet_account_id' => $wallet->id,
            'operation_key' => 'op-1',
            'reference' => 'ref-1',
            'type' => 'reserve',
            'amount_minor' => '1000',
            'currency' => 'NGN',
            'available_before_minor' => '10000',
            'available_after_minor' => '9000',
            'held_before_minor' => '0',
            'held_after_minor' => '1000',
        ]);

        $result = app(WalletAccountingReconciliationService::class)->reconcile($wallet->fresh());

        $this->assertTrue($result['balanced']);
        $this->assertSame(1, $result['movement_count']);
    }

    public function test_wallet_reconciliation_rejects_a_broken_movement_chain(): void
    {
        $wallet = WalletAccount::create([
            'user_id' => User::factory()->create()->id,
            'currency' => 'NGN',
            'available_minor' => '7000',
            'held_minor' => '3000',
            'status' => 'active',
        ]);

        WalletMovement::create([
            'wallet_account_id' => $wallet->id,
            'operation_key' => 'op-1',
            'reference' => 'ref-1',
            'type' => 'reserve',
            'amount_minor' => '3000',
            'currency' => 'NGN',
            'available_before_minor' => '10000',
            'available_after_minor' => '7000',
            'held_before_minor' => '0',
            'held_after_minor' => '3000',
        ]);

        WalletMovement::create([
            'wallet_account_id' => $wallet->id,
            'operation_key' => 'op-2',
            'reference' => 'ref-2',
            'type' => 'reserve',
            'amount_minor' => '1000',
            'currency' => 'NGN',
            'available_before_minor' => '6000',
            'available_after_minor' => '5000',
            'held_before_minor' => '3000',
            'held_after_minor' => '4000',
        ]);

        $this->expectException(RuntimeException::class);

        app(WalletAccountingReconciliationService::class)->reconcile($wallet);
    }
}
