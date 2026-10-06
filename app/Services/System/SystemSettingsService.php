<?php

namespace App\Services\System;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemSettingsService
{
    private ?array $resolved = null;

    public function all(): array
    {
        if ($this->resolved !== null) return $this->resolved;

        $settings = [
            'platform_name' => (string) config('app.name', 'SEMIZZY ONE'),
            'support_email' => (string) config('mail.from.address', ''),
            'support_notice' => '',
            'default_timezone' => (string) config('app.default_timezone', config('app.timezone', 'UTC')),
            'theme_key' => 'modern-corporate',
            'theme_primary' => '#2563EB',
            'skin_default' => 'light',
            'theme_custom_light' => [],
            'theme_custom_dark' => [],
        ];

        try {
            if (Schema::hasTable('system_settings')) {
                $stored = SystemSetting::query()->whereIn('key', array_keys($settings))->pluck('value', 'key');
                foreach (['platform_name','support_email','support_notice','default_timezone','theme_key','theme_primary','skin_default'] as $key) {
                    if (isset($stored[$key]) && is_string($stored[$key]) && $stored[$key] !== '') $settings[$key] = $stored[$key];
                }
                foreach (['theme_custom_light','theme_custom_dark'] as $key) {
                    if (isset($stored[$key]) && is_string($stored[$key])) {
                        $decoded = json_decode($stored[$key], true);
                        if (is_array($decoded)) $settings[$key] = $decoded;
                    }
                }
            }
        } catch (Throwable) {}

        if (! in_array($settings['default_timezone'], timezone_identifiers_list(), true)) $settings['default_timezone'] = 'UTC';
        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $settings['theme_primary'])) $settings['theme_primary'] = '#2563EB';
        $allowed = ['opay-inspired','palmpay-inspired','kuda-inspired','moniepoint-inspired','stripe-inspired','premium-fintech','modern-corporate','clean-saas','vibrant-tech','luxury-executive','custom'];
        if (! in_array($settings['theme_key'], $allowed, true)) $settings['theme_key'] = 'modern-corporate';
        if (! in_array($settings['skin_default'], ['light','dark'], true)) $settings['skin_default'] = 'light';

        return $this->resolved = $settings;
    }
}
