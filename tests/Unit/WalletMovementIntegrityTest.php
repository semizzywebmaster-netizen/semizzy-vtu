<?php

namespace Tests\Unit;

use App\Models\WalletMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class WalletMovementIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_movement_normalizes_currency_and_integer_amounts(): void
    {
        $walletId = $this->createWallet();

        $movement = WalletMovement::create([
            'wallet_account_id' => $walletId,
            'operation_key' => 'test:normalize',
            'reference' => 'test-reference',
            'type' => 'reserve',
            'amount_minor' => '000100',
            'currency' => 'ngn',
            'available_before_minor' => '001000',
            'available_after_minor' => '000900',
            'held_before_minor' => '000000',
            'held_after_minor' => '000100',
            'metadata' => ['source' => 'test'],
        ]);

        $this->assertSame('100', $movement->amount_minor);
        $this->assertSame('1000', $movement->available_before_minor);
        $this->assertSame('900', $movement->available_after_minor);
        $this->assertSame('NGN', $movement->currency);
    }

    public function test_wallet_movement_cannot_be_modified_or_deleted(): void
    {
        $walletId = $this->createWallet();

        $movement = WalletMovement::create([
            'wallet_account_id' => $walletId,
            'operation_key' => 'test:immutable',
            'reference' => 'immutable-reference',
            'type' => 'reserve',
            'amount_minor' => '100',
            'currency' => 'NGN',
            'available_before_minor' => '1000',
            'available_after_minor' => '900',
            'held_before_minor' => '0',
            'held_after_minor' => '100',
        ]);

        try {
            $movement->update(['amount_minor' => '200']);
            $this->fail('Wallet movement update should be rejected.');
        } catch (LogicException $e) {
            $this->assertSame('Wallet movements are immutable.', $e->getMessage());
        }

        $this->expectException(LogicException::class);
        $movement->delete();
    }

    public function test_wallet_movement_rejects_invalid_amounts(): void
    {
        $this->expectException(RuntimeException::class);

        WalletMovement::create([
            'wallet_account_id' => $this->createWallet(),
            'operation_key' => 'test:invalid',
            'reference' => 'invalid-reference',
            'type' => 'reserve',
            'amount_minor' => '-1',
            'currency' => 'NGN',
            'available_before_minor' => '1000',
            'available_after_minor' => '1001',
            'held_before_minor' => '0',
            'held_after_minor' => '0',
        ]);
    }

    private function createWallet(): int
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Wallet Movement Test User',
            'email' => 'wallet-movement-'.uniqid().'@example.test',
            'password' => password_hash('password', PASSWORD_BCRYPT),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('wallet_accounts')->insertGetId([
            'user_id' => $userId,
            'currency' => 'NGN',
            'available_minor' => '1000',
            'held_minor' => '0',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
