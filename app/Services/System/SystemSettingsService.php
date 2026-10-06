<?php

namespace App\Services\System;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemSettingsService
{
    /** @var array<string, string>|null */
    private ?array $resolved = null;

    public function all(): array
    {
        if ($this->resolved !== null) return $this->resolved;

        $settings = [
            'platform_name' => (string) config('app.name', 'SEMIZZY ONE'),
            'support_email' => (string) config('mail.from.address', ''),
            'support_notice' => '',
            'default_timezone' => (string) config('app.default_timezone', config('app.timezone', 'UTC')),
            'theme_key' => 'ocean-blue',
            'theme_primary' => '#2563EB',
        ];

        try {
            if (Schema::hasTable('system_settings')) {
                $stored = SystemSetting::query()->whereIn('key', array_keys($settings))->pluck('value', 'key');
                foreach (array_keys($settings) as $key) {
                    if (isset($stored[$key]) && is_string($stored[$key]) && $stored[$key] !== '') {
                        $settings[$key] = $stored[$key];
                    }
                }
            }
        } catch (Throwable) {}

        if (! in_array($settings['default_timezone'], timezone_identifiers_list(), true)) {
            $settings['default_timezone'] = (string) config('app.default_timezone', 'UTC');
        }
        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $settings['theme_primary'])) {
            $settings['theme_primary'] = '#2563EB';
        }
        if (! in_array($settings['theme_key'], ['ocean-blue', 'emerald', 'royal-purple', 'crimson', 'sunset-orange', 'custom'], true)) {
            $settings['theme_key'] = 'custom';
        }

        return $this->resolved = $settings;
    }
}
