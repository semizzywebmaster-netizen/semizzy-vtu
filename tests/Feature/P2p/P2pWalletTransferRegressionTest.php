<?php

namespace Tests\Feature\P2p;

use App\Models\User;
use App\Models\WalletAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Semizzy\Addons\P2p\Services\P2pTransferService;
use Tests\TestCase;

class P2pWalletTransferRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_debits_and_credits_wallets_once_for_idempotent_retry(): void
    {
        [$sender, $recipient] = $this->usersWithWallets('10000', '500');
        $service = app(P2pTransferService::class);

        $first = $service->transfer($sender->id, $recipient->username, '2500', 'test transfer', 'transfer-idem-1');
        $retry = $service->transfer($sender->id, $recipient->username, '2500', 'test transfer', 'transfer-idem-1');

        $this->assertSame($first->id, $retry->id);
        $this->assertSame('7500', WalletAccount::where('user_id', $sender->id)->value('available_minor'));
        $this->assertSame('3000', WalletAccount::where('user_id', $recipient->id)->value('available_minor'));
        $this->assertDatabaseCount('p2p_transfers', 1);
        $this->assertDatabaseCount('wallet_movements', 2);
    }

    public function test_transfer_with_reused_key_and_different_amount_is_rejected_without_extra_movement(): void
    {
        [$sender, $recipient] = $this->usersWithWallets('10000', '500');
        $service = app(P2pTransferService::class);
        $service->transfer($sender->id, $recipient->username, '2500', null, 'transfer-idem-2');

        try {
            $service->transfer($sender->id, $recipient->username, '3000', null, 'transfer-idem-2');
            $this->fail('An idempotency key cannot be reused for a different transfer amount.');
        } catch (RuntimeException $exception) {
            $this->assertSame('This idempotency key has already been used for a different transfer.', $exception->getMessage());
        }

        $this->assertSame('7500', WalletAccount::where('user_id', $sender->id)->value('available_minor'));
        $this->assertSame('3000', WalletAccount::where('user_id', $recipient->id)->value('available_minor'));
        $this->assertDatabaseCount('p2p_transfers', 1);
        $this->assertDatabaseCount('wallet_movements', 2);
    }

    public function test_insufficient_funds_roll_back_transfer_and_leave_both_wallets_unchanged(): void
    {
        [$sender, $recipient] = $this->usersWithWallets('100', '500');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient wallet balance.');

        try {
            app(P2pTransferService::class)->transfer($sender->id, $recipient->username, '250', null, 'transfer-insufficient-1');
        } finally {
            $this->assertSame('100', WalletAccount::where('user_id', $sender->id)->value('available_minor'));
            $this->assertSame('500', WalletAccount::where('user_id', $recipient->id)->value('available_minor'));
            $this->assertDatabaseCount('p2p_transfers', 0);
            $this->assertDatabaseCount('wallet_movements', 0);
        }
    }

    private function usersWithWallets(string $senderBalance, string $recipientBalance): array
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        WalletAccount::create([
            'user_id' => $sender->id,
            'currency' => 'NGN',
            'available_minor' => $senderBalance,
            'held_minor' => '0',
            'status' => 'active',
        ]);
        WalletAccount::create([
            'user_id' => $recipient->id,
            'currency' => 'NGN',
            'available_minor' => $recipientBalance,
            'held_minor' => '0',
            'status' => 'active',
        ]);

        return [$sender, $recipient];
    }
}
