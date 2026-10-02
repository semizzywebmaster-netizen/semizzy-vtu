<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logger_uses_request_correlation_id_and_redacts_nested_secrets(): void
    {
        $user = User::create([
            'name' => 'Audit User',
            'email' => 'audit-user@example.test',
            'password' => 'Strong-Password-123!',
            'role' => 'ADMIN',
            'status' => 'active',
        ]);
        $request = Request::create('/test', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('request_id', 'corr-audit-123');

        $event = app(AuditLogger::class)->record('test.audit', null, [
            'operation' => 'provider.updated',
            'credentials' => ['api_key' => 'never-store-this'],
            'nested' => ['password' => 'never-store-this-either', 'safe' => 'visible'],
        ], $request);

        $this->assertSame('corr-audit-123', $event->request_id);
        $this->assertSame($user->id, $event->actor_id);
        $this->assertSame('[REDACTED]', $event->context['credentials']);
        $this->assertSame('[REDACTED]', $event->context['nested']['password']);
        $this->assertSame('visible', $event->context['nested']['safe']);
        $this->assertStringNotContainsString('never-store-this', json_encode($event->context));
        $this->assertDatabaseHas('audit_events', ['id' => $event->id, 'event' => 'test.audit']);
    }
}
