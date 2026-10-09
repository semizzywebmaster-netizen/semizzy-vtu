<?php

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletMovement;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Semizzy\Addons\Payments\Models\PaymentGatewayProvider;
use Semizzy\Addons\Payments\Models\PaymentIntent;
use Semizzy\Addons\Payments\Services\PaymentRefundSettlementService;
use RuntimeException;
use Tests\TestCase;

class PaymentRefundSettlementServiceTest extends TestCase
{
    use DatabaseMigrations;

    private bool $createdProviderTable = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('payment_gateway_providers')) {
            Schema::create('payment_gateway_providers', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->string('driver');
                $table->string('base_url')->nullable();
                $table->text('credentials')->nullable();
                $table->json('capabilities')->nullable();
                $table->unsignedInteger('priority')->default(100);
                $table->unsignedInteger('weight')->default(100);
                $table->boolean('enabled')->default(false);
                $table->boolean('paused')->default(false);
                $table->boolean('maintenance')->default(false);
                $table->unsignedInteger('failure_count')->default(0);
                $table->timestamp('cooldown_until')->nullable();
                $table->timestamp('last_health_check_at')->nullable();
                $table->timestamp('last_success_at')->nullable();
                $table->timestamp('last_failure_at')->nullable();
                $table->text('last_error')->nullable();
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->index(['enabled', 'paused', 'maintenance', 'priority'], 'pay_gateway_state_priority_idx');
            });
            $this->createdProviderTable = true;
        }
    }

    protected function tearDown(): void
    {
        if ($this->createdProviderTable && Schema::hasTable('payment_gateway_providers')) {
            Schema::drop('payment_gateway_providers');
        }
        parent::tearDown();
    }

    public function test_verified_full_refund_reverses_wallet_credit_once(): void
    {
        [$payment, $wallet, $movement] = $this->paidFundingPayment('500', 'provider-refund-123');
        $this->fakeRefundStatus('provider-refund-123', 'completed-bank-transfer');
        $actor = User::factory()->create();
        $service = app(PaymentRefundSettlementService::class);

        $first = $service->settleVerified($payment, 'provider-refund-123', 'Customer refund approved', $actor);
        $retry = $service->settleVerified($payment, 'provider-refund-123', 'Retry of same confirmed refund', $actor);

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
        [$payment, $wallet] = $this->paidFundingPayment('500', 'refund-pending-123');
        $this->fakeRefundStatus('refund-pending-123', 'completed');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Provider refund is not confirmed finally successful; wallet accounting was not changed.');

        try {
            app(PaymentRefundSettlementService::class)->settleVerified($payment, 'refund-pending-123', 'Refund requested');
        } finally {
            $this->assertSame('paid', $payment->fresh()->status);
            $this->assertSame('500', (string) $wallet->fresh()->available_minor);
            $this->assertDatabaseCount('wallet_movements', 1);
        }
    }

    public function test_confirmed_provider_refund_with_spent_wallet_requires_manual_reconciliation(): void
    {
        [$payment, $wallet] = $this->paidFundingPayment('100', 'provider-refund-spent-wallet');
        $this->fakeRefundStatus('provider-refund-spent-wallet', 'completed-bank-transfer');

        try {
            app(PaymentRefundSettlementService::class)->settleVerified(
                $payment,
                'provider-refund-spent-wallet',
                'Provider confirmed refund after wallet funds were spent'
            );
            $this->fail('A refund must not overdraw the wallet when credited funds were already spent.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Provider refund is confirmed, but wallet reversal could not be completed safely; manual reconciliation is required.',
                $exception->getMessage()
            );
        }

        $freshPayment = $payment->fresh();
        $this->assertSame('paid', $freshPayment->status);
        $this->assertNull($freshPayment->refunded_at);
        $this->assertSame('manual_review_required', $freshPayment->metadata['refund_accounting_status']);
        $this->assertTrue($freshPayment->metadata['refund_reconciliation_required']);
        $this->assertSame('provider-refund-spent-wallet', $freshPayment->metadata['refund_provider_reference']);
        $this->assertSame('100', (string) $wallet->fresh()->available_minor);
        $this->assertDatabaseCount('wallet_movements', 1);
    }


    private function fakeRefundStatus(string $reference, string $status): void
    {
        Http::fake([
            'https://api.flutterwave.com/v3/refunds*' => Http::response([
                'status' => 'success',
                'data' => [[
                    'flw_ref' => $reference,
                    'transaction_id' => 123,
                    'amount_refunded' => '5.00',
                    'status' => $status,
                ]],
            ], 200),
        ]);
    }

    private function paidFundingPayment(string $currentAvailableMinor = '500', string $refundReference = 'provider-refund-123'): array
    {
        $provider = PaymentGatewayProvider::query()->create([
            'name' => 'Flutterwave Test',
            'code' => 'FLW-REFUND-TEST',
            'driver' => 'flutterwave',
            'base_url' => 'https://api.flutterwave.com/v3',
            'credentials' => ['secret_key' => 'test-secret'],
            'capabilities' => ['refund'],
            'enabled' => true,
            'paused' => false,
            'maintenance' => false,
            'settings' => [],
        ]);
        $user = User::factory()->create();
        $wallet = WalletAccount::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'available_minor' => $currentAvailableMinor,
            'held_minor' => '0',
            'status' => 'active',
        ]);
        $payment = PaymentIntent::create([
            'user_id' => $user->id,
            'wallet_account_id' => $wallet->id,
            'provider_id' => $provider->id,
            'provider_reference' => 'FLW-ORIGINAL-REF-123',
            'reference' => 'PAY-REFUND-'.uniqid(),
            'purpose' => 'wallet_funding',
            'currency' => 'NGN',
            'amount_minor' => '500',
            'status' => 'paid',
            'paid_at' => now(),
            'metadata' => [
                'refund_requested_at' => now()->toISOString(),
                'refund_provider' => $provider->code,
                'refund_provider_reference' => $refundReference,
                'provider_transaction_id' => '123',
                'refund_accounting_status' => 'pending_provider_confirmation',
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
