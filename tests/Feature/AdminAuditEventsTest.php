<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_audit_events_with_secrets_redacted(): void
    {
        $admin = $this->makeUser('audit-admin@example.test', 'ADMIN');
        AuditEvent::create([
            'actor_id' => $admin->id,
            'event' => 'provider.updated',
            'request_id' => 'req-audit-123',
            'context' => ['provider' => 'Example', 'api_key' => 'must-not-display', 'nested' => ['password' => 'secret-value']],
        ]);

        $this->actingAs($admin)->get('/admin/audit-events?event=provider')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/AuditEvents')
                ->where('events.total', 1)
                ->where('events.data.0.context.api_key', '[REDACTED]')
                ->where('events.data.0.context.nested.password', '[REDACTED]'));
    }

    public function test_regular_user_cannot_view_audit_events(): void
    {
        $user = $this->makeUser('audit-user@example.test', 'USER');
        $this->actingAs($user)->get('/admin/audit-events')->assertForbidden();
    }

    private function makeUser(string $email, string $role): User
    {
        return User::create([
            'name' => 'Audit Viewer',
            'email' => $email,
            'password' => 'Strong-Password-123!',
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
