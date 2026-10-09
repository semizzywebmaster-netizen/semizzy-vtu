<?php

namespace Tests\Feature;

use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_login_is_audited_without_recording_email_or_password(): void
    {
        $this->post('/login', [
            'email' => 'audit-user@example.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $event = SecurityEvent::where('event', 'auth.login.failed')->latest('id')->firstOrFail();

        $this->assertSame('warning', $event->severity);
        $this->assertArrayNotHasKey('email', $event->context);
        $this->assertArrayNotHasKey('password', $event->context);
        $this->assertNotEmpty($event->request_id);
    }

    public function test_successful_login_and_logout_are_audited(): void
    {
        User::create([
            'name' => 'Audit User',
            'email' => 'audit-success@example.test',
            'password' => Hash::make('Strong-Test-Password-123!'),
            'role' => 'USER',
            'status' => 'active',
        ]);

        $this->post('/login', [
            'email' => 'audit-success@example.test',
            'password' => 'Strong-Test-Password-123!',
        ])->assertRedirect();

        $this->assertDatabaseHas('security_events', ['event' => 'auth.login.success']);

        $this->post('/logout')->assertRedirect('/');
        $this->assertDatabaseHas('security_events', ['event' => 'auth.logout']);
    }

    public function test_password_reset_request_is_audited_without_email(): void
    {
        $this->post('/forgot-password/otp', [
            'email' => 'unknown@example.test',
            'otp_channel' => 'email',
        ])->assertSessionHas('otp_sent');

        $event = SecurityEvent::where('event', 'auth.password_recovery.otp_requested')->latest('id')->firstOrFail();

        $this->assertArrayNotHasKey('email', $event->context);
    }
}
