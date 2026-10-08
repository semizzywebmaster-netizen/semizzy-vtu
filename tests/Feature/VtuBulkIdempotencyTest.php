<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceProduct;
use App\Models\User;
use App\Models\VtuTransaction;
use App\Services\Vtu\VtuBulkService;
use App\Services\Vtu\VtuPayloadValidator;
use App\Services\Vtu\VtuTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Mockery;
use Tests\TestCase;

class VtuBulkIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_item_idempotency_keys_are_rejected_before_processing(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(VtuBulkService::class)->execute(1, [
            ['product_id' => 1, 'idempotency_key' => 'item-1', 'payload' => []],
            ['product_id' => 1, 'idempotency_key' => 'item-1', 'payload' => []],
        ], 'USER', 'bulk-duplicate-items');
    }

    public function test_changed_bulk_quote_is_rejected_before_processing(): void
    {
        $user = User::create([
            'name' => 'Bulk Quote Test',
            'email' => 'bulk-quote@example.test',
            'password' => 'password',
            'role' => 'USER',
            'status' => 'active',
        ]);

        $category = ServiceCategory::create([
            'key' => 'bulk-quote-test',
            'name' => 'Bulk Quote Test',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        $service = Service::create([
            'category_id' => $category->id,
            'key' => 'data',
            'name' => 'Data',
            'enabled' => true,
        ]);

        $product = ServiceProduct::create([
            'service_id' => $service->id,
            'key' => 'quote-plan',
            'name' => '1GB Data',
            'provider_cost' => '100.00',
            'currency' => 'NGN',
            'enabled' => true,
        ]);

        $this->mock(VtuPayloadValidator::class, function ($mock): void {
            $mock->shouldReceive('validate')->once()->andReturn([]);
        });

        $this->mock(VtuTransactionService::class, function ($mock): void {
            $mock->shouldReceive('quote')->once()->andReturn([
                'customer_price' => '150.00',
                'currency' => 'NGN',
            ]);
        });

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('expired or changed');

        app(VtuBulkService::class)->execute($user->id, [[
            'product_id' => $product->id,
            'idempotency_key' => 'quote-item-1',
            'payload' => ['phone' => '08012345678', 'network' => 'mtn', 'plan' => '1GB Data'],
        ]], 'USER', 'bulk-quote-key', str_repeat('a', 64));
    }

    public function test_duplicate_airtime_or_data_recipients_are_rejected_server_side(): void
    {
        $user = User::create([
            'name' => 'Bulk Recipient Test',
            'email' => 'bulk-recipient@example.test',
            'password' => 'password',
            'role' => 'USER',
            'status' => 'active',
        ]);

        $category = ServiceCategory::create([
            'key' => 'bulk-vtu-test',
            'name' => 'Bulk VTU Test',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        $service = Service::create([
            'category_id' => $category->id,
            'key' => 'data',
            'name' => 'Data',
            'enabled' => true,
        ]);

        $product = ServiceProduct::create([
            'service_id' => $service->id,
            'key' => 'data-plan',
            'name' => '1GB Data',
            'provider_cost' => '100.00',
            'currency' => 'NGN',
            'enabled' => true,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('Duplicate recipient numbers are not allowed');

        app(VtuBulkService::class)->execute($user->id, [
            ['product_id' => $product->id, 'idempotency_key' => 'data-1', 'payload' => ['phone' => '08012345678', 'network' => 'mtn', 'plan' => '1GB Data']],
            ['product_id' => $product->id, 'idempotency_key' => 'data-2', 'payload' => ['phone' => '+2348012345678', 'network' => 'mtn', 'plan' => '1GB Data']],
        ], 'USER', 'bulk-duplicate-recipient');
    }

    public function test_same_bulk_key_with_different_request_is_rejected(): void
    {
        $user = User::create([
            'name' => 'Bulk Test',
            'email' => 'bulk-idempotency@example.test',
            'password' => 'password',
            'role' => 'USER',
            'status' => 'active',
        ]);

        $category = ServiceCategory::create([
            'key' => 'bulk-test',
            'name' => 'Bulk Test',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        $service = Service::create([
            'category_id' => $category->id,
            'key' => 'bulk-test-service',
            'name' => 'Bulk Test Service',
            'enabled' => true,
        ]);

        $product = ServiceProduct::create([
            'service_id' => $service->id,
            'key' => 'bulk-product',
            'name' => 'Bulk Product',
            'provider_cost' => '100.00',
            'currency' => 'NGN',
            'enabled' => true,
        ]);

        $tx = new VtuTransaction([
            'status' => 'successful',
            'total_minor' => '10000',
            'failure_message' => null,
        ]);

        $this->mock(VtuPayloadValidator::class, function ($mock): void {
            $mock->shouldReceive('validate')->once();
        });

        $this->mock(VtuTransactionService::class, function ($mock) use ($product, $tx, $user): void {
            $mock->shouldReceive('create')
                ->once()
                ->with($user->id, Mockery::on(fn (ServiceProduct $candidate): bool => $candidate->id === $product->id), ['phone' => '08000000000'], 'USER', 'item-1')
                ->andReturn($tx);
            $mock->shouldReceive('process')->once()->with($tx)->andReturn($tx);
        });

        $service = app(VtuBulkService::class);
        $service->execute($user->id, [[
            'product_id' => $product->id,
            'idempotency_key' => 'item-1',
            'payload' => ['phone' => '08000000000'],
        ]], 'USER', 'bulk-replay-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Bulk idempotency key has already been used for a different request.');

        app(VtuBulkService::class)->execute($user->id, [[
            'product_id' => $product->id,
            'idempotency_key' => 'item-1',
            'payload' => ['phone' => '08111111111'],
        ]], 'USER', 'bulk-replay-key');
    }
}
