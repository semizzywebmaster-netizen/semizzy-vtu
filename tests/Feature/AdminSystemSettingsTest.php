<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_safe_settings(): void
    {
        $admin = $this->makeUser('settings-admin@example.test', 'ADMIN');

        $this->actingAs($admin)->get('/admin/settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Settings')
                ->where('settings.platform_name', 'SEMIZZY ONE')
                ->where('settings.default_timezone', config('app.timezone')));
    }

    public function test_admin_can_save_allowlisted_settings_and_audit_keys_only(): void
    {
        $admin = $this->makeUser('settings-save@example.test', 'ADMIN');

        $this->actingAs($admin)->put('/admin/settings', [
            'platform_name' => 'SEMIZZY ONE Core',
            'support_email' => 'help@example.test',
            'support_notice' => 'Support is available weekdays.',
            'default_timezone' => 'Africa/Lagos',
        ])->assertRedirect();

        $this->assertDatabaseHas('system_settings', ['key' => 'platform_name', 'value' => 'SEMIZZY ONE Core', 'is_secret' => 0]);
        $this->assertDatabaseHas('system_settings', ['key' => 'default_timezone', 'value' => 'Africa/Lagos']);
        $event = \App\Models\AuditEvent::query()->where('event', 'admin.system_settings.updated')->firstOrFail();
        $this->assertSame(['setting_keys' => ['platform_name', 'support_email', 'support_notice', 'default_timezone']], $event->context);
    }

    public function test_invalid_timezone_is_rejected_without_writing_settings(): void
    {
        $admin = $this->makeUser('settings-invalid@example.test', 'ADMIN');

        $this->actingAs($admin)->put('/admin/settings', [
            'platform_name' => 'SEMIZZY ONE',
            'support_email' => '',
            'support_notice' => '',
            'default_timezone' => 'Not/A-Timezone',
        ])->assertSessionHasErrors('default_timezone');

        $this->assertSame(0, SystemSetting::query()->count());
    }

    public function test_non_admin_cannot_read_or_update_system_settings(): void
    {
        $user = $this->makeUser('settings-user@example.test', 'USER');

        $this->actingAs($user)->get('/admin/settings')->assertForbidden();
        $this->actingAs($user)->put('/admin/settings', [
            'platform_name' => 'Not allowed',
            'support_email' => '',
            'support_notice' => '',
            'default_timezone' => 'UTC',
        ])->assertForbidden();
        $this->assertSame(0, SystemSetting::query()->count());
    }

    private function makeUser(string $email, string $role): User
    {
        return User::create([
            'name' => 'Settings Test User',
            'email' => $email,
            'password' => 'Strong-Password-123!',
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
