<?php

namespace App\Providers;

use App\Models\SystemSetting;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Crypt;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        try {
            if (! Schema::hasTable('system_settings')) return;
            $raw = SystemSetting::query()->where('key','smtp')->value('value');
            $smtp = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($smtp) || empty($smtp['enabled']) || empty($smtp['host']) || empty($smtp['username']) || empty($smtp['password'])) return;
            try { $smtp['password'] = Crypt::decryptString($smtp['password']); } catch (\Throwable) { return; }

            config([
                'mail.default' => 'platform_smtp',
                'mail.mailers.platform_smtp' => [
                    'transport' => 'smtp',
                    'host' => $smtp['host'],
                    'port' => (int) ($smtp['port'] ?? 587),
                    'encryption' => ($smtp['encryption'] ?? 'tls') === 'null' ? null : ($smtp['encryption'] ?? 'tls'),
                    'username' => $smtp['username'],
                    'password' => $smtp['password'],
                    'timeout' => 15,
                ],
                'mail.from.address' => $smtp['from_address'] ?? config('mail.from.address'),
                'mail.from.name' => $smtp['from_name'] ?? config('mail.from.name'),
            ]);
        } catch (\Throwable) {
            // Keep .env/cPanel mail configuration as the safe fallback.
        }
    }
}
