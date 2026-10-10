<?php

namespace App\Providers;

use App\Models\SystemSetting;
use App\Models\Addon;
use App\Services\Addons\AddonRegistry;
use App\Services\Commercial\CommercialServiceRegistry;
use Semizzy\Addons\MailerSmtp\Services\MailerSmtpService;
use Addons\WhatsAppBot\Observers\VtuTransactionObserver;
use App\Models\VtuTransaction;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Semizzy\Addons\Payments\Services\PaymentGatewayAdapterRegistry;
use Semizzy\Addons\Payments\Adapters\MonnifyPaymentGatewayAdapter;
use Semizzy\Addons\Payments\Adapters\PaystackPaymentGatewayAdapter;
use Semizzy\Addons\Payments\Adapters\InterswitchPaymentGatewayAdapter;
use Semizzy\Addons\Payments\Adapters\FlutterwavePaymentGatewayAdapter;
use Semizzy\Addons\Payments\Adapters\OpayPaymentGatewayAdapter;
use Semizzy\Addons\Payments\Adapters\KoraPaymentGatewayAdapter;
use Semizzy\Addons\Payments\Adapters\SquadPaymentGatewayAdapter;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CommercialServiceRegistry::class, fn () => new CommercialServiceRegistry());
        $this->app->singleton(PaymentGatewayAdapterRegistry::class, function () {
            $registry = new PaymentGatewayAdapterRegistry();
            $registry->register('paystack', fn () => new PaystackPaymentGatewayAdapter());
            $registry->register('interswitch', fn () => new InterswitchPaymentGatewayAdapter());
            $registry->register('monnify', fn () => new MonnifyPaymentGatewayAdapter());
            $registry->register('flutterwave', fn () => new FlutterwavePaymentGatewayAdapter());
            $registry->register('opay', fn () => new OpayPaymentGatewayAdapter());
            $registry->register('kora', fn () => new KoraPaymentGatewayAdapter());
            $registry->register('squad', fn () => new SquadPaymentGatewayAdapter());
            return $registry;
        });
    }

    public function boot(): void
    {
        // Register addon autoloaders before resolving classes supplied by optional addons.
        // Otherwise the transaction observer may fail to autoload and silently disable notifications.
        try {
            $addons = app(AddonRegistry::class);
            $addons->registerAutoloaders();
        } catch (\Throwable) {
            // Core must still boot if an optional addon manifest is unavailable.
        }

        try {
            VtuTransaction::observe(VtuTransactionObserver::class);
        } catch (\Throwable) {
            // WhatsApp transaction notifications are optional and must never block Core boot.
        }

        try {
            $addons = app(AddonRegistry::class);
            $registry = app(CommercialServiceRegistry::class);
            foreach ($addons->commercialAdapters() as $definition) {
                $addon = Addon::query()
                    ->where('identifier', $definition['addon'])
                    ->where('status', 'active')
                    ->exists();

                if (!$addon) continue;

                $registry->register(
                    app($definition['class']),
                    (int) ($definition['priority'] ?? 100),
                    (array) ($definition['service_keys'] ?? [])
                );
            }
        } catch (\Throwable) {
            // Optional addon adapters must never prevent Core boot.
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
