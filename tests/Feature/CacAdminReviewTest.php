<?php
namespace Tests\Feature;

use App\Models\CacOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CacAdminReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_moves_order_to_provider_ready(): void
    {
        $admin = User::factory()->create(['role'=>'ADMIN','status'=>'active']);
        $order = CacOrder::create([
            'uuid'=>(string) Str::uuid(),
            'reference'=>'CAC-'.Str::random(12),
            'user_id'=>User::factory()->create()->id,
            'service_type'=>'business_name_registration',
            'status'=>'pending_review',
            'idempotency_key'=>(string) Str::uuid(),
            'amount_minor'=>1000,'fee_minor'=>0,'total_minor'=>1000,'currency'=>'NGN',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.cac.orders.review', $order), [
            'action'=>'approve',
            'note'=>'Documents verified.',
        ]);

        $response->assertSessionHas('success');
        $this->assertSame('provider_ready', $order->fresh()->status);
    }
}
