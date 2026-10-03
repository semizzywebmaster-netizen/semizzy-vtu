<?php

namespace App\Services\System;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemSettingsService
{
    /** @var array<string, string>|null */
    private ?array $resolved = null;

    /** @return array{platform_name:string,support_email:string,support_notice:string,default_timezone:string} */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $settings = [
            'platform_name' => (string) config('app.name', 'SEMIZZY ONE'),
            'support_email' => (string) config('mail.from.address', ''),
            'support_notice' => '',
            'default_timezone' => (string) config('app.default_timezone', config('app.timezone', 'UTC')),
        ];

        try {
            if (Schema::hasTable('system_settings')) {
                $stored = SystemSetting::query()
                    ->whereIn('key', array_keys($settings))
                    ->pluck('value', 'key');

                foreach (array_keys($settings) as $key) {
                    if (isset($stored[$key]) && is_string($stored[$key])) {
                        $settings[$key] = $stored[$key];
                    }
                }
            }
        } catch (Throwable) {
            // Public pages and error handling must still render if settings storage is unavailable.
        }

        if (! in_array($settings['default_timezone'], timezone_identifiers_list(), true)) {
            $settings['default_timezone'] = (string) config('app.default_timezone', 'UTC');
        }

        $this->resolved = $settings;

        return $this->resolved;
    }
}
