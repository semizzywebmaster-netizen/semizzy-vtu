<?php

namespace App\Services\System;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SettingsService
{
    private const CACHE_KEY = 'semizzy.settings.registry.v1';

    /** @var array<string,array<string,mixed>> */
    private array $definitions = [];

    public function __construct()
    {
        $this->definitions = $this->coreDefinitions();

        try {
            if (Schema::hasTable('system_settings')) {
                foreach (SystemSetting::query()->where('key', 'like', 'setting.registry.%')->get() as $row) {
                    $key = substr((string) $row->key, strlen('setting.registry.'));
                    $definition = json_decode((string) $row->value, true);
                    if ($key !== '' && is_array($definition)) {
                        $this->definitions[$key] = array_replace($this->definitions[$key] ?? [], $definition);
                    }
                }
            }
        } catch (Throwable) {
            // Configuration must remain usable even during an early migration.
        }
    }

    public function definitions(): array
    {
        return $this->definitions;
    }

    public function definition(string $key): ?array
    {
        return $this->definitions[$key] ?? null;
    }

    public function register(string $key, array $definition): array
    {
        $key = trim($key);
        if ($key === '' || !preg_match('/^[a-z0-9][a-z0-9._-]{1,127}$/', $key)) {
            throw new \InvalidArgumentException('Invalid setting key.');
        }

        $merged = array_replace([
            'name' => $key,
            'category' => 'system',
            'description' => '',
            'type' => 'string',
            'default' => null,
            'required' => false,
            'editable' => true,
            'secret' => false,
            'options' => [],
            'validation' => [],
            'dependencies' => [],
        ], $definition);

        $this->definitions[$key] = $merged;

        $this->persistRegistryDefinition($key, $merged);

        return $merged;
    }

    public function all(bool $includeSecrets = false): array
    {
        $out = [];

        foreach ($this->definitions as $key => $definition) {
            $value = $this->get($key, null);
            $out[$key] = [
                'key' => $key,
                'name' => $definition['name'] ?? $key,
                'category' => $definition['category'] ?? 'system',
                'description' => $definition['description'] ?? '',
                'type' => $definition['type'] ?? 'string',
                'default' => $definition['default'] ?? null,
                'value' => (($definition['secret'] ?? false) && !$includeSecrets) ? null : $value,
                'is_secret' => (bool) ($definition['secret'] ?? false),
                'editable' => (bool) ($definition['editable'] ?? true),
                'options' => $definition['options'] ?? [],
                'validation' => $definition['validation'] ?? [],
                'dependencies' => $definition['dependencies'] ?? [],
                'source' => str_starts_with($key, 'vtu.digital-services.') ? 'addon' : 'core',
            ];
        }

        return $out;
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        $definition = $this->definitions[$key] ?? null;
        if (!$definition) return $fallback;

        $default = array_key_exists('default', $definition) ? $definition['default'] : $fallback;

        try {
            if (!Schema::hasTable('system_settings')) return $default;
            $row = SystemSetting::query()->where('key', $key)->first();
            if (!$row) return $default;

            $value = $this->decodeStoredValue((string) $row->value, $definition);
            return $this->coerce($value, $definition['type'] ?? 'string', $default);
        } catch (Throwable) {
            return $default;
        }
    }

    public function set(string $key, mixed $value, ?string $actor = null): mixed
    {
        $definition = $this->definitions[$key] ?? null;
        if (!$definition) throw new \InvalidArgumentException("Unknown setting: {$key}");
        if (($definition['editable'] ?? true) !== true) throw new \RuntimeException("Setting {$key} is not editable.");

        $this->assertDependencies($definition);
        $normalized = $this->validateAndNormalize($key, $value, $definition);

        $stored = $this->encodeStoredValue($normalized, $definition);

        SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $stored,
                'type' => (string) ($definition['type'] ?? 'string'),
                'is_secret' => (bool) ($definition['secret'] ?? false),
            ]
        );

        $this->invalidate();

        return $normalized;
    }

    public function bulkUpdate(array $values, ?string $actor = null): array
    {
        $normalized = [];
        foreach ($values as $key => $value) {
            if (!is_string($key)) throw new \InvalidArgumentException('Every setting key must be a string.');
            $definition = $this->definitions[$key] ?? null;
            if (!$definition) throw new \InvalidArgumentException("Unknown setting: {$key}");
            $this->assertDependencies($definition);
            $normalized[$key] = $this->validateAndNormalize($key, $value, $definition);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($normalized) {
            foreach ($normalized as $key => $value) {
                $definition = $this->definitions[$key];
                SystemSetting::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $this->encodeStoredValue($value, $definition),
                        'type' => (string) ($definition['type'] ?? 'string'),
                        'is_secret' => (bool) ($definition['secret'] ?? false),
                    ]
                );
            }
            $this->invalidate();
            return $normalized;
        });
    }

    public function forget(string $key): void
    {
        SystemSetting::query()->where('key', $key)->delete();
        $this->invalidate();
    }

    public function invalidate(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function validateAndNormalize(string $key, mixed $value, array $definition): mixed
    {
        $type = (string) ($definition['type'] ?? 'string');
        $rules = ['nullable'];

        $rules[] = match ($type) {
            'boolean' => 'boolean',
            'integer', 'currency' => 'integer',
            'decimal' => 'numeric',
            'email' => 'email',
            'url' => 'url',
            'color' => ['regex:/^#[0-9A-Fa-f]{6}$/'],
            'json' => ['nullable'],
            'secret', 'string', 'select' => 'string',
            'multiselect' => 'array',
            'datetime' => 'date',
            default => 'string',
        };

        $data = ['value' => $value];
        $validator = Validator::make($data, ['value' => $rules]);
        if ($validator->fails()) {
            throw new \InvalidArgumentException("Invalid value for setting {$key}.");
        }

        if ($type === 'integer' || $type === 'currency') {
            $value = (int) $value;
            if ($value < 0 && ($definition['min'] ?? 0) >= 0) throw new \InvalidArgumentException("Setting {$key} cannot be negative.");
        }

        if ($type === 'decimal') $value = (string) $value;
        if ($type === 'boolean') $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        if ($type === 'json') {
            if (is_string($value)) json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        }

        $options = $definition['options'] ?? [];
        if (in_array($type, ['select'], true) && $options && !in_array($value, array_keys($options), true) && !in_array($value, $options, true)) {
            throw new \InvalidArgumentException("Invalid option for setting {$key}.");
        }
        if ($type === 'multiselect' && $options) {
            foreach ($value as $item) {
                if (!in_array($item, array_keys($options), true) && !in_array($item, $options, true)) {
                    throw new \InvalidArgumentException("Invalid option for setting {$key}.");
                }
            }
        }

        if (isset($definition['min']) && is_numeric($value) && $value < $definition['min']) throw new \InvalidArgumentException("Setting {$key} is below its minimum.");
        if (isset($definition['max']) && is_numeric($value) && $value > $definition['max']) throw new \InvalidArgumentException("Setting {$key} is above its maximum.");

        return $value;
    }

    private function decodeStoredValue(string $raw, array $definition): mixed
    {
        if (($definition['secret'] ?? false) && $raw !== '') {
            try { $raw = Crypt::decryptString($raw); } catch (Throwable) {}
        }

        $type = (string) ($definition['type'] ?? 'string');
        return match ($type) {
            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'integer', 'currency' => is_numeric($raw) ? (int) $raw : null,
            'decimal' => is_numeric($raw) ? (string) $raw : null,
            'json', 'multiselect' => json_decode($raw, true),
            default => $raw,
        };
    }

    private function encodeStoredValue(mixed $value, array $definition): string
    {
        $type = (string) ($definition['type'] ?? 'string');
        $raw = in_array($type, ['json', 'multiselect'], true)
            ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);

        if (($definition['secret'] ?? false) && $raw !== '') {
            $raw = Crypt::encryptString($raw);
        }

        return $raw;
    }

    private function assertDependencies(array $definition): void
    {
        foreach (($definition['dependencies'] ?? []) as $dependency) {
            if (is_string($dependency) && $this->get($dependency, false) !== true) {
                throw new \RuntimeException("Required setting {$dependency} is disabled.");
            }
        }
    }

    private function persistRegistryDefinition(string $key, array $definition): void
    {
        try {
            if (!Schema::hasTable('system_settings')) return;
            SystemSetting::query()->updateOrCreate(
                ['key' => 'setting.registry.'.$key],
                ['value' => json_encode($definition, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'type' => 'json', 'is_secret' => false]
            );
        } catch (Throwable) {}
    }

    private function coerce(mixed $value, string $type, mixed $default): mixed
    {
        if ($value === null) return $default;
        if ($type === 'json' || $type === 'multiselect') return is_array($value) ? $value : $default;
        return $value;
    }

    private function coreDefinitions(): array
    {
        return [
            'platform.name' => ['name'=>'Platform name','category'=>'platform','description'=>'Global platform identity.','type'=>'string','default'=>'SEMIZZY ONE','max'=>80],
            'platform.support_email' => ['name'=>'Support email','category'=>'platform','description'=>'Primary support email.','type'=>'email','default'=>''],
            'platform.support_notice' => ['name'=>'Support notice','category'=>'platform','description'=>'Global support notice.','type'=>'string','default'=>'','max'=>500],
            'platform.timezone' => ['name'=>'Default timezone','category'=>'platform','description'=>'Application timezone.','type'=>'select','default'=>'Africa/Lagos','options'=>array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers())],
            'finance.kyc_bvn_lookup_charge_minor' => ['name'=>'BVN lookup charge','category'=>'finance','description'=>'Exact wallet charge in minor currency units.','type'=>'currency','default'=>0,'min'=>0],
            'finance.kyc_nin_lookup_charge_minor' => ['name'=>'NIN lookup charge','category'=>'finance','description'=>'Exact wallet charge in minor currency units.','type'=>'currency','default'=>0,'min'=>0],
            'appearance.theme_key' => ['name'=>'Theme','category'=>'appearance','description'=>'Global theme key.','type'=>'select','default'=>'modern-corporate','options'=>['opay-inspired','palmpay-inspired','modern-corporate','clean-saas','luxury-executive','custom']],
            'appearance.theme_primary' => ['name'=>'Primary colour','category'=>'appearance','description'=>'Global primary HEX colour.','type'=>'color','default'=>'#2563EB'],
            'appearance.skin_default' => ['name'=>'Default skin','category'=>'appearance','description'=>'Default light/dark skin.','type'=>'select','default'=>'light','options'=>['light','dark']],
            'security.new_device_verification' => ['name'=>'New device verification','category'=>'security','description'=>'Require verification for new devices.','type'=>'boolean','default'=>true],
            'security.single_device_session' => ['name'=>'Single-device session','category'=>'security','description'=>'Log the user out of previous devices after verified new-device login.','type'=>'boolean','default'=>true],
            'security.referral_device_limit' => ['name'=>'Referral registrations per device','category'=>'security','description'=>'Maximum referral registrations from one device.','type'=>'integer','default'=>2,'min'=>1,'max'=>20],
            'communication.email.enabled' => ['name'=>'Transactional email','category'=>'communication','description'=>'Allow transactional email delivery.','type'=>'boolean','default'=>true],
            'communication.email.from_name' => ['name'=>'Email sender name','category'=>'communication','description'=>'Default sender display name.','type'=>'string','default'=>'SEMIZZY ONE','max'=>120],
            'appearance.theme_custom_light' => ['name'=>'Custom light palette','category'=>'appearance','description'=>'Custom light theme palette.','type'=>'json','default'=>[]],
            'appearance.theme_custom_dark' => ['name'=>'Custom dark palette','category'=>'appearance','description'=>'Custom dark theme palette.','type'=>'json','default'=>[]],
            'platform.business' => ['name'=>'Business information','category'=>'platform','description'=>'Platform business contact and address information.','type'=>'json','default'=>[]],
            'platform.social' => ['name'=>'Social links','category'=>'platform','description'=>'Official platform social media links.','type'=>'json','default'=>[]],
            'appearance.assets' => ['name'=>'Brand assets','category'=>'appearance','description'=>'Logo, favicon, banner and hero assets.','type'=>'json','default'=>[]],
            'appearance.footer_menu' => ['name'=>'Footer menu','category'=>'appearance','description'=>'Global footer navigation.','type'=>'json','default'=>[]],
            'communication.smtp' => ['name'=>'SMTP profiles','category'=>'communication','description'=>'Transactional email SMTP profiles. Individual passwords are encrypted before storage.','type'=>'json','default'=>[]],
        ];
    }
}
