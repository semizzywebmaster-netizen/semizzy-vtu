<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\FinancialOperation;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceProduct;
use App\Models\User;
use App\Models\VtuTransaction;
use App\Models\WalletAccount;
use App\Services\Audit\AuditLogger;
use App\Services\Providers\ProviderResult;
use App\Services\Vtu\VtuProviderGateway;
use App\Services\Vtu\VtuTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VtuProviderFailureRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function makeTransaction(string $key): array
    {
        $user = User::create([
            'name' => 'VTU Failure Test',
            'email' => $key.'@example.test',
            'password' => 'password',
            'role' => 'USER',
            'status' => 'active',
        ]);

        $wallet = WalletAccount::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'available_minor' => '0',
            'held_minor' => '10000',
            'status' => 'active',
        ]);

        $category = ServiceCategory::create([
            'key' => 'failure-'.$key,
            'name' => 'Failure Tests',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        $service = Service::create([
            'category_id' => $category->id,
            'key' => 'failure-service-'.$key,
            'name' => 'Failure Service',
            'enabled' => true,
        ]);

        $product = ServiceProduct::create([
            'service_id' => $service->id,
            'key' => 'failure-product-'.$key,
            'name' => 'Failure Product',
            'provider_cost' => '100.00',
            'currency' => 'NGN',
            'enabled' => true,
        ]);

        $provider = ApiProvider::create([
            'identifier' => 'failure-provider-'.$key,
            'display_name' => 'Failure Provider',
            'enabled' => true,
            'paused' => false,
            'verification_status' => 'live_verified',
            'integration_status' => 'live_verified',
        ]);

        $operation = FinancialOperation::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'FAIL-'.$key,
            'user_id' => $user->id,
            'type' => 'vtu.purchase',
            'status' => 'processing',
            'amount_minor' => '10000',
            'currency' => 'NGN',
            'idempotency_key' => $key,
            'metadata' => [],
        ]);

        $tx = VtuTransaction::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'VTU-FAIL-'.$key,
            'user_id' => $user->id,
            'service_id' => $service->id,
            'service_product_id' => $product->id,
            'financial_operation_id' => $operation->id,
            'api_provider_id' => $provider->id,
            'idempotency_key' => $key,
            'status' => 'processing',
            'amount_minor' => '10000',
            'fee_minor' => '0',
            'total_minor' => '10000',
            'currency' => 'NGN',
            'customer_tier' => 'USER',
            'request_payload' => ['phone' => '08000000000'],
            'metadata' => [],
        ]);

        $operation->metadata = ['vtu_transaction_id' => $tx->id];
        $operation->save();

        return [$tx, $wallet, $operation, $provider];
    }

    public function test_explicit_provider_failure_releases_wallet_hold_once(): void
    {
        [$tx, $wallet, $operation] = $this->makeTransaction('explicit-failure');

        $this->mock(VtuProviderGateway::class, function ($mock): void {
            $mock->shouldReceive('initiate')->once()->andReturn(new ProviderResult(
                accepted: false,
                status: 'FAILED',
                message: 'Provider rejected the request.',
            ));
        });
        $this->mock(AuditLogger::class, function ($mock): void {
            $mock->shouldReceive('record')->once();
        });

        $result = app(VtuTransactionService::class)->process($tx);
        $wallet->refresh();
        $operation->refresh();

        $this->assertSame('failed', $result->status);
        $this->assertSame('10000', (string) $wallet->available_minor);
        $this->assertSame('0', (string) $wallet->held_minor);
        $this->assertSame('failed', $operation->status);
        $this->assertTrue((bool) (($result->metadata ?? [])['financial_settlement_applied'] ?? false));
    }

    public function test_unknown_provider_state_keeps_hold_and_requery_can_settle_successfully(): void
    {
        [$tx, $wallet, $operation, $provider] = $this->makeTransaction('unknown-requery');

        $gateway = $this->mock(VtuProviderGateway::class);
        $gateway->shouldReceive('initiate')->once()->andReturn(new ProviderResult(
            accepted: false,
            status: 'UNKNOWN',
            message: 'Provider timeout.',
            providerReference: 'PROVIDER-PENDING-1',
            duplicateRisk: true,
            providerId: $provider->id,
        ));
        $gateway->shouldReceive('requery')->once()->andReturn(new ProviderResult(
            accepted: true,
            status: 'SUCCESSFUL',
            providerReference: 'PROVIDER-RECOVERED-1',
            data: ['status' => 'successful'],
            providerId: $provider->id,
        ));
        $this->mock(AuditLogger::class, function ($mock): void {
            $mock->shouldReceive('record')->once();
        });

        $service = app(VtuTransactionService::class);
        $pending = $service->process($tx);
        $wallet->refresh();

        $this->assertSame('pending', $pending->status);
        $this->assertSame('0', (string) $wallet->available_minor);
        $this->assertSame('10000', (string) $wallet->held_minor);
        $this->assertSame('UNKNOWN_PROVIDER_STATE', $pending->failure_code);

        $pending->refresh();
        $recovered = $service->requery($pending);
        $wallet->refresh();
        $operation->refresh();

        $this->assertSame('successful', $recovered->status);
        $this->assertSame('0', (string) $wallet->held_minor);
        $this->assertSame('0', (string) $wallet->available_minor);
        $this->assertSame('completed', $operation->status);
        $this->assertSame('PROVIDER-RECOVERED-1', $recovered->provider_reference);
    }

    public function test_unknown_provider_state_requery_failure_preserves_pending_and_hold(): void
    {
        [$tx, $wallet, $operation, $provider] = $this->makeTransaction('unknown-failure');

        $gateway = $this->mock(VtuProviderGateway::class);
        $gateway->shouldReceive('initiate')->once()->andReturn(new ProviderResult(
            accepted: false,
            status: 'UNKNOWN',
            message: 'Provider timeout.',
            duplicateRisk: true,
            providerId: $provider->id,
        ));
        $gateway->shouldReceive('requery')->once()->andThrow(new \RuntimeException('Provider status unavailable.'));
        $this->mock(AuditLogger::class);

        $service = app(VtuTransactionService::class);
        $pending = $service->process($tx);
        $pending->refresh();

        try {
            $service->requery($pending);
            $this->fail('Expected requery failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Provider status unavailable.', $e->getMessage());
        }

        $pending->refresh();
        $wallet->refresh();

        $this->assertSame('pending', $pending->status);
        $this->assertSame('UNKNOWN_PROVIDER_STATE', $pending->failure_code);
        $this->assertSame('10000', (string) $wallet->held_minor);
        $this->assertSame('0', (string) $wallet->available_minor);
    }
}
