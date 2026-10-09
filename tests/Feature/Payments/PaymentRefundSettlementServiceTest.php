<?php

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Semizzy\Addons\Payments\Models\PaymentIntent;
use Semizzy\Addons\Payments\Services\PaymentRefundSettlementService;
use RuntimeException;
use Tests\TestCase;

class PaymentRefundSettlementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_full_refund_reverses_wallet_credit_once(): void
    {
        [$payment, $wallet, $movement] = $this->paidFundingPayment();
        $actor = User::factory()->create();
        $service = app(PaymentRefundSettlementService::class);

        $first = $service->settleVerified($payment, 'provider-refund-123', 'success', 'Customer refund approved', $actor);
        $retry = $service->settleVerified($payment, 'provider-refund-123', 'success', 'Retry of same confirmed refund', $actor);

        $this->assertSame($first->id, $retry->id);
        $this->assertSame('refunded', $first->status);
        $this->assertNotNull($first->refunded_at);
        $this->assertSame('settled', $first->metadata['refund_accounting_status']);
        $this->assertSame('provider-refund-123', $first->metadata['refund_provider_reference']);
        $this->assertSame('0', (string) $wallet->fresh()->available_minor);
        $this->assertDatabaseCount('wallet_movements', 2);
        $this->assertSame((string) $movement->id, (string) WalletMovement::query()
            ->where('operation_key', 'wallet:reversal:'.$movement->id)
            ->firstOrFail()->metadata['reversal_of_movement_id']);
    }

    public function test_unconfirmed_provider_refund_status_cannot_change_payment_or_wallet(): void
    {
        [$payment, $wallet] = $this->paidFundingPayment();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Provider refund status is not confirmed successful.');

        try {
            app(PaymentRefundSettlementService::class)->settleVerified($payment, 'refund-pending-123', 'pending', 'Refund requested');
        } finally {
            $this->assertSame('paid', $payment->fresh()->status);
            $this->assertSame('500', (string) $wallet->fresh()->available_minor);
            $this->assertDatabaseCount('wallet_movements', 1);
        }
    }

    private function paidFundingPayment(): array
    {
        $user = User::factory()->create();
        $wallet = WalletAccount::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'available_minor' => '500',
            'held_minor' => '0',
            'status' => 'active',
        ]);
        $payment = PaymentIntent::create([
            'user_id' => $user->id,
            'wallet_account_id' => $wallet->id,
            'reference' => 'PAY-REFUND-'.uniqid(),
            'purpose' => 'wallet_funding',
            'currency' => 'NGN',
            'amount_minor' => '500',
            'status' => 'paid',
            'paid_at' => now(),
            'metadata' => [
                'refund_requested_at' => now()->toISOString(),
                'refund_provider' => 'test-provider',
                'refund_accounting_status' => 'pending_core_wallet_reversal',
            ],
        ]);
        $movement = WalletMovement::create([
            'wallet_account_id' => $wallet->id,
            'operation_key' => 'payment:intent:'.$payment->id,
            'reference' => 'PAY-CREDIT-TEST-'.$payment->id,
            'type' => 'payment_funding',
            'amount_minor' => '500',
            'currency' => 'NGN',
            'available_before_minor' => '0',
            'available_after_minor' => '500',
            'held_before_minor' => '0',
            'held_after_minor' => '0',
            'metadata' => ['payment_intent_id' => $payment->id],
        ]);

        return [$payment, $wallet, $movement];
    }
}
