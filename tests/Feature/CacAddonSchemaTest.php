<?php

namespace Tests\Feature;

use App\Models\CacOrder;
use App\Models\CacOrderAttempt;
use App\Models\CacOrderDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CacAddonSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cac_order_schema_supports_idempotency_attempts_and_documents(): void
    {
        $user = User::create([
            'name' => 'CAC Schema User',
            'email' => 'cac-schema@example.test',
            'password' => 'Strong-Password-123!',
            'role' => 'USER',
            'status' => 'active',
        ]);

        $order = CacOrder::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'CAC-TEST-'.Str::upper(Str::random(12)),
            'user_id' => $user->id,
            'service_type' => 'business_name_registration',
            'status' => 'pending_review',
            'idempotency_key' => 'cac-test-'.Str::uuid(),
            'amount_minor' => 100000,
            'fee_minor' => 0,
            'total_minor' => 100000,
            'currency' => 'NGN',
        ]);

        $attempt = CacOrderAttempt::create([
            'cac_order_id' => $order->id,
            'attempt_number' => 1,
            'operation' => 'create_order',
            'status' => 'pending',
        ]);

        $document = CacOrderDocument::create([
            'cac_order_id' => $order->id,
            'document_type' => 'identity',
            'storage_disk' => 'local',
            'storage_path' => 'cac/test/identity.pdf',
            'status' => 'uploaded',
        ]);

        $this->assertDatabaseHas('cac_orders', ['id' => $order->id, 'status' => 'pending_review']);
        $this->assertDatabaseHas('cac_order_attempts', ['id' => $attempt->id, 'cac_order_id' => $order->id]);
        $this->assertDatabaseHas('cac_order_documents', ['id' => $document->id, 'cac_order_id' => $order->id]);
        $this->assertTrue($order->fresh()->attempts->contains($attempt->id));
        $this->assertTrue($order->fresh()->documents->contains($document->id));
    }
}
