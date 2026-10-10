<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_can_use_bounded_global_search_endpoint(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMIN',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson('/admin/global-search?q=semizzy');

        $response->assertOk()
            ->assertJsonStructure(['query', 'results'])
            ->assertJsonPath('query', 'semizzy');

        $this->assertLessThanOrEqual(15, count($response->json('results')));
    }

    public function test_global_search_rejects_queries_shorter_than_two_characters(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMIN',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)->getJson('/admin/global-search?q=a')->assertUnprocessable();
    }

    public function test_regular_users_cannot_access_admin_global_search(): void
    {
        $user = User::factory()->create(['role' => 'USER']);

        $this->actingAs($user)->getJson('/admin/global-search?q=semizzy')->assertForbidden();
    }
}
