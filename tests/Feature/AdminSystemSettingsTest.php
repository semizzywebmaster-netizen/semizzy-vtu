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
            'theme_key' => 'modern-corporate',
            'theme_primary' => '#4338CA',
            'support_email' => 'help@example.test',
            'support_notice' => 'Support is available weekdays.',
            'default_timezone' => 'Africa/Lagos',
        ])->assertRedirect();

        $this->assertDatabaseHas('system_settings', ['key' => 'platform_name', 'value' => 'SEMIZZY ONE Core', 'is_secret' => 0]);
        $this->assertDatabaseHas('system_settings', ['key' => 'default_timezone', 'value' => 'Africa/Lagos']);
        $event = \App\Models\AuditEvent::query()->where('event', 'admin.system_settings.updated')->firstOrFail();
        $this->assertContains('platform_name', $event->context['setting_keys']);
        $this->assertNotContains('smtp', $event->context['setting_keys']);
    }

    public function test_saved_public_settings_are_shared_and_timezone_is_applied(): void
    {
        $admin = $this->makeUser('settings-public@example.test', 'ADMIN');
        SystemSetting::query()->create(['key' => 'platform_name', 'value' => 'Semizzy Platform', 'type' => 'string', 'is_secret' => false]);
        SystemSetting::query()->create(['key' => 'support_email', 'value' => 'help@example.test', 'type' => 'string', 'is_secret' => false]);
        SystemSetting::query()->create(['key' => 'support_notice', 'value' => 'Scheduled maintenance tonight.', 'type' => 'string', 'is_secret' => false]);
        SystemSetting::query()->create(['key' => 'default_timezone', 'value' => 'Africa/Lagos', 'type' => 'string', 'is_secret' => false]);

        $this->actingAs($admin)->get('/')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Welcome')
                ->where('platform.platform_name', 'Semizzy Platform')
                ->where('platform.support_email', 'help@example.test')
                ->where('platform.support_notice', 'Scheduled maintenance tonight.'));

        $this->assertSame('Africa/Lagos', config('app.timezone'));
        $this->assertSame('Africa/Lagos', date_default_timezone_get());
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
        $user = User::create([
            'name' => 'Settings Test User',
            'email' => $email,
            'password' => 'Strong-Password-123!',
            'role' => $role,
            'status' => 'active',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
