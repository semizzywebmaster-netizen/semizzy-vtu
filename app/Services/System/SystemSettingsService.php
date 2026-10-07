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

        $registry = app(SettingsService::class);

        $settings = [
            'platform_name' => (string) $registry->get('platform.name', config('app.name', 'SEMIZZY ONE')),
            'support_email' => (string) $registry->get('platform.support_email', ''),
            'support_notice' => (string) $registry->get('platform.support_notice', ''),
            'kyc_bvn_lookup_charge_minor' => (int) $registry->get('finance.kyc_bvn_lookup_charge_minor', 0),
            'kyc_nin_lookup_charge_minor' => (int) $registry->get('finance.kyc_nin_lookup_charge_minor', 0),
            'kyc_bvn_lookup_charge' => '0.00',
            'kyc_nin_lookup_charge' => '0.00',
            'default_timezone' => (string) $registry->get('platform.timezone', config('app.default_timezone', config('app.timezone', 'UTC'))),
            'theme_key' => (string) $registry->get('appearance.theme_key', 'modern-corporate'),
            'theme_primary' => (string) $registry->get('appearance.theme_primary', '#2563EB'),
            'skin_default' => (string) $registry->get('appearance.skin_default', 'light'),
            'theme_custom_light' => (array) $registry->get('appearance.theme_custom_light', []),
            'theme_custom_dark' => (array) $registry->get('appearance.theme_custom_dark', []),
            'business' => (array) $registry->get('platform.business', ['phone'=>'','whatsapp'=>'','email'=>'','address'=>'','website'=>'']),
            'social' => (array) $registry->get('platform.social', ['facebook'=>'','instagram'=>'','x'=>'','youtube'=>'','tiktok'=>'','linkedin'=>'']),
            'assets' => (array) $registry->get('appearance.assets', ['logo'=>'','favicon'=>'','banner'=>'','hero'=>'']),
            'footer_menu' => (array) $registry->get('appearance.footer_menu', [
                ['key'=>'home','label'=>'Home','href'=>'/dashboard','icon'=>'⌂'],
                ['key'=>'services','label'=>'Services','href'=>'/vtu','icon'=>'✦'],
                ['key'=>'transactions','label'=>'Transactions','href'=>'/transactions','icon'=>'↔'],
                ['key'=>'notifications','label'=>'Alerts','href'=>'/notifications','icon'=>'♧'],
                ['key'=>'profile','label'=>'Profile','href'=>'/profile','icon'=>'◎'],
            ]),
            'smtp' => (array) $registry->get('communication.smtp', [
                'enabled'=>false,
                'strategy'=>'failover',
                'profiles'=>[],
                'health'=>[],
            ]),
        ];

        try {
            if (Schema::hasTable('system_settings')) {
                $stored = SystemSetting::query()->whereIn('key', array_keys($settings))->pluck('value', 'key');
                foreach (['platform_name','support_email','support_notice','default_timezone','theme_key','theme_primary','skin_default'] as $key) {
                    if (isset($stored[$key]) && is_string($stored[$key]) && $stored[$key] !== '') $settings[$key] = $stored[$key];
                }

                foreach (['kyc_bvn_lookup_charge_minor','kyc_nin_lookup_charge_minor'] as $key) {
                    if (isset($stored[$key]) && is_numeric($stored[$key])) {
                        $settings[$key] = max(0, (int) $stored[$key]);
                    }
                }

                foreach (['theme_custom_light','theme_custom_dark','business','social','assets','footer_menu','smtp'] as $key) {
                    if (!isset($stored[$key]) || !is_string($stored[$key])) continue;
                    $decoded = json_decode($stored[$key], true);
                    if (!is_array($decoded)) continue;

                    if ($key === 'smtp') {
                        // Backward compatibility with the original single-SMTP object.
                        if (isset($decoded['host']) && !isset($decoded['profiles'])) {
                            $legacy = $decoded;
                            unset($legacy['password']);
                            $decoded = [
                                'enabled' => (bool) ($legacy['enabled'] ?? false),
                                'strategy' => 'failover',
                                'profiles' => [[
                                    'key'=>'legacy',
                                    'name'=>(string) ($legacy['provider'] ?? 'Existing SMTP'),
                                    'provider'=>(string) ($legacy['provider'] ?? 'custom'),
                                    'enabled'=>(bool) ($legacy['enabled'] ?? false),
                                    'priority'=>1,
                                    'weight'=>1,
                                    'host'=>(string) ($legacy['host'] ?? ''),
                                    'port'=>(int) ($legacy['port'] ?? 587),
                                    'encryption'=>(string) ($legacy['encryption'] ?? 'tls'),
                                    'username'=>(string) ($legacy['username'] ?? ''),
                                    'from_address'=>(string) ($legacy['from_address'] ?? ''),
                                    'from_name'=>(string) ($legacy['from_name'] ?? ''),
                                ]],
                            ];
                        } else {
                            foreach (($decoded['profiles'] ?? []) as $i => $profile) {
                                if (is_array($profile)) unset($decoded['profiles'][$i]['password']);
                                unset($decoded['profiles'][$i]['last_error']);
                            }
                        }
                    }

                    $settings[$key] = array_replace_recursive($settings[$key] ?? [], $decoded);
                }
            }
        } catch (Throwable) {}

        if (!in_array($settings['default_timezone'], timezone_identifiers_list(), true)) $settings['default_timezone'] = 'UTC';
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $settings['theme_primary'])) $settings['theme_primary'] = '#2563EB';

        $allowedThemes = ['opay-inspired','palmpay-inspired','modern-corporate','clean-saas','luxury-executive','custom'];
        if (!in_array($settings['theme_key'], $allowedThemes, true)) $settings['theme_key'] = 'modern-corporate';
        if (!is_array($settings['footer_menu']) || count($settings['footer_menu']) !== 5) $settings['footer_menu'] = [
            ['key'=>'home','label'=>'Home','href'=>'/dashboard','icon'=>'⌂'],
            ['key'=>'services','label'=>'Services','href'=>'/vtu','icon'=>'✦'],
            ['key'=>'transactions','label'=>'Transactions','href'=>'/transactions','icon'=>'↔'],
            ['key'=>'notifications','label'=>'Alerts','href'=>'/notifications','icon'=>'♧'],
            ['key'=>'profile','label'=>'Profile','href'=>'/profile','icon'=>'◎'],
        ];
        if (!in_array($settings['skin_default'], ['light','dark'], true)) $settings['skin_default'] = 'light';
        if (!in_array($settings['smtp']['strategy'] ?? 'failover', ['failover','roundrobin'], true)) $settings['smtp']['strategy'] = 'failover';
        if (!is_array($settings['smtp']['health'] ?? null)) $settings['smtp']['health'] = [];

        return $this->resolved = $settings;
    }
}
