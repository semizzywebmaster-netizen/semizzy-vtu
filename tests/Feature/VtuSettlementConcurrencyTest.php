<?php

namespace Tests\Feature;

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
use Tests\TestCase;

class VtuSettlementConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_terminal_transaction_cannot_settle_wallet_twice(): void
    {
        $user = User::create([
            'name' => 'Settlement Test',
            'email' => 'settlement-test@example.test',
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
            'key' => 'settlement-test',
            'name' => 'Settlement Test',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        $service = Service::create([
            'category_id' => $category->id,
            'key' => 'settlement-test-service',
            'name' => 'Settlement Test Service',
            'enabled' => true,
        ]);

        $product = ServiceProduct::create([
            'service_id' => $service->id,
            'key' => 'settlement-product',
            'name' => 'Settlement Product',
            'provider_cost' => '100.00',
            'currency' => 'NGN',
            'enabled' => true,
        ]);

        $operation = FinancialOperation::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'reference' => 'SETTLE-TEST-1',
            'user_id' => $user->id,
            'type' => 'vtu.purchase',
            'status' => 'processing',
            'amount_minor' => '10000',
            'currency' => 'NGN',
            'idempotency_key' => 'settle-test-1',
            'metadata' => ['vtu_transaction_id' => null],
        ]);

        $tx = VtuTransaction::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'reference' => 'VTU-SETTLE-TEST-1',
            'user_id' => $user->id,
            'service_id' => $service->id,
            'service_product_id' => $product->id,
            'financial_operation_id' => $operation->id,
            'idempotency_key' => 'settle-test-1',
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

        $this->mock(VtuProviderGateway::class, function ($mock): void {
            $mock->shouldReceive('initiate')
                ->once()
                ->andReturn(new ProviderResult(
                    accepted: true,
                    status: 'SUCCESSFUL',
                    providerReference: 'PROVIDER-SETTLE-1',
                    data: ['ok' => true],
                ));
        });

        $this->mock(AuditLogger::class, function ($mock): void {
            $mock->shouldReceive('record')->once();
        });

        $serviceUnderTest = app(VtuTransactionService::class);

        $first = $serviceUnderTest->process($tx);
        $second = $serviceUnderTest->process($first->fresh());

        $wallet->refresh();
        $operation->refresh();
        $first->refresh();

        $this->assertSame('successful', $first->status);
        $this->assertSame('0', (string) $wallet->held_minor);
        $this->assertSame('0', (string) $wallet->available_minor);
        $this->assertSame('completed', $operation->status);
        $this->assertTrue((bool) (($first->metadata ?? [])['financial_settlement_applied'] ?? false));
        $this->assertSame($first->id, $second->id);
    }
}
