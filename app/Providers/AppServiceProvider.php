<?php

namespace App\Providers;

use App\Models\SystemSetting;
use App\Models\Addon;
use App\Services\Commercial\CommercialServiceRegistry;
use Addons\BusinessAgentMerchantReseller\Services\BusinessCommercialAdapter;
use Addons\BusinessAgentMerchantReseller\Services\BusinessCommercialService;
use Semizzy\Addons\MailerSmtp\Services\MailerSmtpService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CommercialServiceRegistry::class, fn () => new CommercialServiceRegistry());
        $this->app->singleton(BusinessCommercialService::class, fn () => new BusinessCommercialService());
    }

    public function boot(): void
    {
        try {
            $registry = app(CommercialServiceRegistry::class);
            $registry->register(app(BusinessCommercialAdapter::class));
        } catch (\Throwable) {
            // Business commercial addon is optional; Core remains usable without it.
        }

        try {
            if (Schema::hasTable('addons') && Addon::query()->where('identifier','mailer.smtp')->where('status','active')->exists()) {
                if (app(MailerSmtpService::class)->configure()) return;
            }
        } catch (\Throwable) {
            // Fall back to the legacy Core SMTP settings when the addon is unavailable.
        }

        try {
            if (! Schema::hasTable('system_settings')) return;

            $raw = SystemSetting::query()->where('key','smtp')->value('value');
            $smtp = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($smtp) || empty($smtp['enabled']) || !is_array($smtp['profiles'] ?? null)) return;

            $profiles = collect($smtp['profiles'])
                ->filter(fn ($p) => is_array($p) && !empty($p['enabled']) && !empty($p['host']))
                ->sortBy(fn ($p) => (int)($p['priority'] ?? 1))
                ->values();

            $mailers = [];
            $names = [];

            foreach ($profiles as $profile) {
                $key = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($profile['key'] ?? 'smtp'));
                $name = 'platform_smtp_'.$key;
                $password = (string)($profile['password'] ?? '');

                try {
                    if ($password !== '') $password = Crypt::decryptString($password);
                } catch (\Throwable) {
                    continue;
                }

                if ($password === '' || empty($profile['username'])) continue;

                $mailers[$name] = [
                    'transport'=>'smtp',
                    'host'=>(string)$profile['host'],
                    'port'=>(int)($profile['port'] ?? 587),
                    'encryption'=>($profile['encryption'] ?? 'tls') === 'null' ? null : ($profile['encryption'] ?? 'tls'),
                    'username'=>(string)$profile['username'],
                    'password'=>$password,
                    'timeout'=>15,
                ];
                $names[] = $name;
            }

            if (!$names) return;

            $strategy = ($smtp['strategy'] ?? 'failover') === 'roundrobin' ? 'roundrobin' : 'failover';

            config([
                'mail.mailers' => array_merge(config('mail.mailers', []), $mailers, [
                    'platform_multi_smtp' => [
                        'transport'=>$strategy,
                        'mailers'=>$names,
                        'retry_after'=>60,
                    ],
                ]),
                'mail.default' => 'platform_multi_smtp',
            ]);

            $first = $profiles->first();
            if (is_array($first)) {
                config([
                    'mail.from.address' => $first['from_address'] ?? config('mail.from.address'),
                    'mail.from.name' => $first['from_name'] ?? config('mail.from.name'),
                ]);
            }
        } catch (\Throwable) {
            // Keep .env/cPanel mail configuration as the safe fallback.
        }
    }
}
