<?php

namespace App\Services\System;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Schema;

class FeatureControlService
{
    private ?array $features = null;

    public function enabled(string $key, bool $default = false): bool
    {
        $item = $this->feature($key);
        return $item === null ? $default : (bool) $item['enabled'];
    }

    public function feature(string $key): ?array
    {
        $this->load();
        return $this->features[$key] ?? null;
    }

    public function all(): array
    {
        $this->load();
        return $this->features;
    }

    public function register(string $key, array $definition = []): array
    {
        $key = trim($key);
        abort_if($key === '' || !preg_match('/^[a-z0-9][a-z0-9._-]{1,120}$/i', $key), 422, 'Invalid feature key.');

        $existing = SystemSetting::query()->where('key', 'feature.'.$key)->first();
        $value = $existing ? json_decode((string) $existing->value, true) : null;
        $payload = array_merge([
            'key' => $key,
            'name' => $definition['name'] ?? $key,
            'category' => $definition['category'] ?? 'General',
            'description' => $definition['description'] ?? '',
            'source' => $definition['source'] ?? 'Core',
            'enabled' => array_key_exists('default_enabled', $definition) ? (bool) $definition['default_enabled'] : true,
            'default_enabled' => array_key_exists('default_enabled', $definition) ? (bool) $definition['default_enabled'] : true,
            'dependencies' => array_values(array_filter((array) ($definition['dependencies'] ?? []))),
        ], is_array($value) ? $value : []);

        SystemSetting::query()->updateOrCreate(
            ['key' => 'feature.'.$key],
            ['value' => json_encode($payload, JSON_UNESCAPED_SLASHES), 'type' => 'json', 'is_secret' => false]
        );
        $this->features = null;
        return $payload;
    }

    public function setEnabled(string $key, bool $enabled): array
    {
        $feature = $this->feature($key);
        if (!$feature) {
            throw new \RuntimeException('Feature is not registered.');
        }

        if (!$enabled) {
            $dependents = collect($this->features)
                ->filter(fn (array $f) => in_array($key, $f['dependencies'] ?? [], true) && !empty($f['enabled']))
                ->pluck('name')->values()->all();
            if ($dependents) {
                throw new \RuntimeException('Disable dependent features first: '.implode(', ', $dependents).'.');
            }
        }

        $feature['enabled'] = $enabled;
        SystemSetting::query()->where('key', 'feature.'.$key)->update([
            'value' => json_encode($feature, JSON_UNESCAPED_SLASHES),
        ]);
        $this->features[$key] = $feature;
        return $feature;
    }

    private function load(): void
    {
        if ($this->features !== null) return;

        $defaults = [
            ['registration.enabled','User registration','Users','Allow new account registration.',true,[]],
            ['kyc.enabled','KYC','KYC','Allow KYC applications and verification.',true,[]],
            ['kyc.lookup.bvn','BVN verification lookup','KYC','Allow billable BVN lookups.',true,['kyc.enabled']],
            ['kyc.lookup.nin','NIN verification lookup','KYC','Allow billable NIN lookups.',true,['kyc.enabled']],
            ['finance.enabled','Wallet & finance','Finance','Allow wallet and financial operations.',true,[]],
            ['vtu.enabled','VTU services','VTU','Allow VTU service processing.',true,[]],
            ['support.enabled','Support centre','Communication','Allow support tickets.',true,[]],
            ['notifications.enabled','Notifications','Communication','Allow user notifications.',true,[]],
            ['api.enabled','API access','API','Allow API access.',true,[]],
            ['referrals.enabled','Referrals','Users','Allow referral features.',true,[]],
            ['referral.rewards.enabled','Referral rewards','Users','Allow referral rewards.',true,['referrals.enabled']],
            ['maintenance.enabled','Maintenance mode','System','Put ordinary users into maintenance mode.',false,[]],
        ];

        $this->features = [];
        if (!Schema::hasTable('system_settings')) {
            foreach ($defaults as $d) $this->features[$d[0]] = $this->definition($d);
            return;
        }

        $keys = collect($defaults)->map(fn ($d) => 'feature.'.$d[0])->all();
        $stored = SystemSetting::query()->whereIn('key', $keys)->pluck('value','key');
        foreach ($defaults as $d) {
            $key = $d[0];
            $decoded = isset($stored['feature.'.$key]) ? json_decode((string) $stored['feature.'.$key], true) : null;
            $this->features[$key] = is_array($decoded) ? array_merge($this->definition($d), $decoded) : $this->definition($d);
        }

        foreach (SystemSetting::query()->where('key','like','feature.%')->get() as $setting) {
            $decoded = json_decode((string) $setting->value, true);
            if (is_array($decoded) && !empty($decoded['key'])) $this->features[$decoded['key']] = array_merge($this->features[$decoded['key']] ?? [], $decoded);
        }
    }

    private function definition(array $d): array
    {
        return ['key'=>$d[0],'name'=>$d[1],'category'=>$d[2],'description'=>$d[3],'enabled'=>(bool)$d[4],'default_enabled'=>(bool)$d[4],'dependencies'=>$d[5],'source'=>'Core'];
    }
}
