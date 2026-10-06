<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\CacOrder;
use App\Models\CacOrderDocument;
use App\Models\CacServiceProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CacAdminReviewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'ADMIN', 'status' => 'active', 'email_verified_at' => now()]);
    }

    private function activateAddon(): void
    {
        Addon::create([
            'identifier' => 'cac.business-services',
            'name' => 'CAC Business Services',
            'version' => '1.0.0',
            'status' => 'active',
            'enabled' => true,
            'manifest' => [],
        ]);
    }

    public function test_review_moves_order_to_provider_ready_when_no_documents_are_required(): void
    {
        $this->activateAddon();
        $admin = $this->admin();
        $order = CacOrder::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'CAC-' . Str::random(12),
            'user_id' => User::factory()->create()->id,
            'service_type' => 'business_name_registration',
            'status' => 'pending_review',
            'idempotency_key' => (string) Str::uuid(),
            'amount_minor' => 1000, 'fee_minor' => 0, 'total_minor' => 1000, 'currency' => 'NGN',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.cac.orders.review', $order), [
            'action' => 'approve',
            'note' => 'Documents verified.',
        ]);

        $response->assertSessionHas('success');
        $this->assertSame('provider_ready', $order->fresh()->status);
    }

    public function test_approval_is_blocked_when_required_document_is_missing(): void
    {
        $this->activateAddon();
        $admin = $this->admin();
        $user = User::factory()->create();
        $product = CacServiceProduct::create([
            'identifier' => 'cac-test-required-doc',
            'name' => 'CAC Test',
            'service_type' => 'business_name_registration',
            'currency' => 'NGN',
            'requirements' => ['documents' => ['id_card']],
            'enabled' => true,
        ]);
        $order = CacOrder::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'CAC-' . Str::random(12),
            'user_id' => $user->id,
            'cac_service_product_id' => $product->id,
            'service_type' => $product->service_type,
            'status' => 'pending_review',
            'idempotency_key' => (string) Str::uuid(),
            'amount_minor' => 1000, 'fee_minor' => 0, 'total_minor' => 1000, 'currency' => 'NGN',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.cac.orders.review', $order), ['action' => 'approve']);

        $response->assertSessionHasErrors('documents');
        $this->assertSame('pending_review', $order->fresh()->status);
    }

    public function test_rejected_document_is_not_sufficient_for_approval(): void
    {
        $this->activateAddon();
        $admin = $this->admin();
        $user = User::factory()->create();
        $product = CacServiceProduct::create([
            'identifier' => 'cac-test-rejected-doc',
            'name' => 'CAC Test',
            'service_type' => 'business_name_registration',
            'currency' => 'NGN',
            'requirements' => ['documents' => ['id_card']],
            'enabled' => true,
        ]);
        $order = CacOrder::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'CAC-' . Str::random(12),
            'user_id' => $user->id,
            'cac_service_product_id' => $product->id,
            'service_type' => $product->service_type,
            'status' => 'pending_review',
            'idempotency_key' => (string) Str::uuid(),
            'amount_minor' => 1000, 'fee_minor' => 0, 'total_minor' => 1000, 'currency' => 'NGN',
        ]);
        CacOrderDocument::create([
            'cac_order_id' => $order->id,
            'document_type' => 'id_card',
            'storage_disk' => 'local',
            'storage_path' => 'private/cac/test/id.pdf',
            'original_name' => 'id.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'status' => 'rejected',
            'checksum' => hash('sha256', 'test'),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.cac.orders.review', $order), ['action' => 'approve']);

        $response->assertSessionHasErrors('documents');
        $this->assertSame('pending_review', $order->fresh()->status);
    }

    public function test_accepted_document_allows_approval(): void
    {
        $this->activateAddon();
        $admin = $this->admin();
        $user = User::factory()->create();
        $product = CacServiceProduct::create([
            'identifier' => 'cac-test-accepted-doc',
            'name' => 'CAC Test',
            'service_type' => 'business_name_registration',
            'currency' => 'NGN',
            'requirements' => ['documents' => ['id_card']],
            'enabled' => true,
        ]);
        $order = CacOrder::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'CAC-' . Str::random(12),
            'user_id' => $user->id,
            'cac_service_product_id' => $product->id,
            'service_type' => $product->service_type,
            'status' => 'pending_review',
            'idempotency_key' => (string) Str::uuid(),
            'amount_minor' => 1000, 'fee_minor' => 0, 'total_minor' => 1000, 'currency' => 'NGN',
        ]);
        CacOrderDocument::create([
            'cac_order_id' => $order->id,
            'document_type' => 'id_card',
            'storage_disk' => 'local',
            'storage_path' => 'private/cac/test/id.pdf',
            'original_name' => 'id.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'status' => 'accepted',
            'checksum' => hash('sha256', 'test'),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.cac.orders.review', $order), ['action' => 'approve']);

        $response->assertSessionHas('success');
        $this->assertSame('provider_ready', $order->fresh()->status);
    }
}
