<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Finance\WalletCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class WalletCreditIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_reusing_credit_operation_key_with_different_amount_is_rejected(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Wallet Credit Idempotency Test',
            'email' => 'wallet-credit-'.uniqid().'@example.test',
            'password' => password_hash('password', PASSWORD_BCRYPT),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('wallet_accounts')->insert([
            'user_id' => $userId,
            'currency' => 'NGN',
            'available_minor' => '1000',
            'held_minor' => '0',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->findOrFail($userId);
        $service = app(WalletCreditService::class);

        $service->credit($user, '10.00', 'payment:intent:42', 'PAY-42', 'payment_funding');

        try {
            $service->credit($user, '20.00', 'payment:intent:42', 'PAY-42', 'payment_funding');
            $this->fail('A reused operation key with a different amount must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'This wallet operation key has already been used for a different credit.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('wallet_accounts', [
            'user_id' => $userId,
            'currency' => 'NGN',
            'available_minor' => '2000',
        ]);
        $this->assertDatabaseCount('wallet_movements', 1);
    }
}
