<?php

namespace Tests\Feature\Payments;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Semizzy\Addons\Payments\Adapters\FlutterwavePaymentGatewayAdapter;
use Semizzy\Addons\Payments\Models\PaymentGatewayProvider;
use Tests\TestCase;

class PaymentRefundVerificationRegressionTest extends TestCase
{
    use RefreshDatabase;

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
                $table->index(['enabled', 'paused', 'maintenance', 'priority']);
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

    public function test_flutterwave_refund_status_is_looked_up_by_exact_provider_refund_reference(): void
    {
        $provider = $this->provider();
        Http::fake([
            'https://api.flutterwave.com/v3/refunds*' => Http::response([
                'status' => 'success',
                'data' => [
                    [
                        'flw_ref' => 'FLW-REFUND-VERIFIED-1',
                        'transaction_id' => 812345,
                        'amount_refunded' => 500,
                        'status' => 'completed-bank-transfer',
                    ],
                ],
            ], 200),
        ]);

        $verified = app(FlutterwavePaymentGatewayAdapter::class)->verifyRefund($provider, 'FLW-REFUND-VERIFIED-1');

        $this->assertSame('FLW-REFUND-VERIFIED-1', $verified['flw_ref']);
        $this->assertSame('completed-bank-transfer', $verified['status']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/refunds')
            && str_contains($request->url(), 'flw_ref=FLW-REFUND-VERIFIED-1'));
    }

    public function test_flutterwave_refund_status_lookup_rejects_a_different_refund_reference(): void
    {
        $provider = $this->provider();
        Http::fake([
            'https://api.flutterwave.com/v3/refunds*' => Http::response([
                'status' => 'success',
                'data' => [
                    ['flw_ref' => 'A-DIFFERENT-REFUND', 'status' => 'completed-bank-transfer', 'amount_refunded' => 500],
                ],
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Flutterwave did not return a refund matching the requested refund reference.');
        app(FlutterwavePaymentGatewayAdapter::class)->verifyRefund($provider, 'FLW-REFUND-VERIFIED-1');
    }

    private function provider(): PaymentGatewayProvider
    {
        return PaymentGatewayProvider::query()->create([
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
    }
}
