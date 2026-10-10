<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;\nuse Illuminate\Database\QueryException;\nuse Illuminate\Support\Facades\DB;
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

    public function test_audit_events_are_append_only_through_eloquent(): void
    {
        $event = AuditEvent::create([
            'event' => 'immutable.audit.test',
            'context' => ['safe' => 'original'],
        ]);

        try {
            $event->update(['event' => 'tampered.audit.test']);
            $this->fail('Audit event updates must be rejected.');
        } catch (\LogicException $exception) {
            $this->assertSame('Audit events are append-only and cannot be modified.', $exception->getMessage());
        }

        try {
            $event->delete();
            $this->fail('Audit event deletion must be rejected.');
        } catch (\LogicException $exception) {
            $this->assertSame('Audit events are append-only and cannot be deleted.', $exception->getMessage());
        }

        $this->assertDatabaseHas('audit_events', [
            'id' => $event->id,
            'event' => 'immutable.audit.test',
        ]);
    }

    public function test_database_rejects_direct_audit_event_updates(): void
    {
        $event = AuditEvent::create(['event' => 'immutable.sql.update']);

        try {
            DB::table('audit_events')->where('id', $event->id)->update(['event' => 'tampered.sql.update']);
            $this->fail('Database trigger must reject direct audit event updates.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('audit_events is append-only', $exception->getMessage());
        }

        $this->assertDatabaseHas('audit_events', ['id' => $event->id, 'event' => 'immutable.sql.update']);
    }

    public function test_database_rejects_direct_audit_event_deletes(): void
    {
        $event = AuditEvent::create(['event' => 'immutable.sql.delete']);

        try {
            DB::table('audit_events')->where('id', $event->id)->delete();
            $this->fail('Database trigger must reject direct audit event deletes.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('audit_events is append-only', $exception->getMessage());
        }

        $this->assertDatabaseHas('audit_events', ['id' => $event->id, 'event' => 'immutable.sql.delete']);
    }

}
