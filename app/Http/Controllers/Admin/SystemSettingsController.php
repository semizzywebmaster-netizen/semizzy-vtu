<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingsController extends Controller
{
    private const KEYS = ['platform_name','support_email','support_notice','default_timezone','theme_key','theme_primary','skin_default','theme_custom_light','theme_custom_dark'];

    public function index(): Response
    {
        $stored = SystemSetting::query()->whereIn('key', self::KEYS)->pluck('value', 'key');
        $decode = static function ($value): array {
            $decoded = is_string($value) ? json_decode($value, true) : $value;
            return is_array($decoded) ? $decoded : [];
        };

        return Inertia::render('Admin/Settings', [
            'settings' => [
                'platform_name' => (string) ($stored['platform_name'] ?? config('app.name', 'SEMIZZY ONE')),
                'support_email' => (string) ($stored['support_email'] ?? config('mail.from.address', '')),
                'support_notice' => (string) ($stored['support_notice'] ?? ''),
                'default_timezone' => (string) ($stored['default_timezone'] ?? config('app.timezone', 'UTC')),
                'theme_key' => (string) ($stored['theme_key'] ?? 'modern-corporate'),
                'theme_primary' => (string) ($stored['theme_primary'] ?? '#2563EB'),
                'skin_default' => in_array(($stored['skin_default'] ?? 'light'), ['light','dark'], true) ? $stored['skin_default'] : 'light',
                'theme_custom_light' => $decode($stored['theme_custom_light'] ?? null),
                'theme_custom_dark' => $decode($stored['theme_custom_dark'] ?? null),
            ],
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'platform_name' => ['required','string','min:2','max:80'],
            'support_email' => ['nullable','email','max:254'],
            'support_notice' => ['nullable','string','max:500'],
            'default_timezone' => ['required','timezone'],
            'theme_key' => ['required','in:opay-inspired,palmpay-inspired,kuda-inspired,moniepoint-inspired,stripe-inspired,premium-fintech,modern-corporate,clean-saas,vibrant-tech,luxury-executive,custom'],
            'theme_primary' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'skin_default' => ['required','in:light,dark'],
            'theme_custom_light' => ['nullable','array'],
            'theme_custom_dark' => ['nullable','array'],
        ]);

        $paletteKeys = ['primary','secondary','accent','background','surface','text','muted','border','success','warning','danger'];
        foreach (['theme_custom_light','theme_custom_dark'] as $field) {
            $palette = $data[$field] ?? [];
            foreach ($paletteKeys as $key) {
                if (isset($palette[$key]) && ! preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $palette[$key])) {
                    return back()->withErrors([$field => 'Every custom colour must be a valid 6-digit HEX colour.'])->withInput();
                }
            }
            $data[$field] = array_intersect_key($palette, array_flip($paletteKeys));
        }

        try {
            foreach (['platform_name','support_email','support_notice','default_timezone','theme_key','theme_primary','skin_default'] as $key) {
                SystemSetting::query()->updateOrCreate(['key'=>$key], ['value'=>$data[$key] ?? '', 'type'=>'string', 'is_secret'=>false]);
            }
            foreach (['theme_custom_light','theme_custom_dark'] as $key) {
                SystemSetting::query()->updateOrCreate(['key'=>$key], ['value'=>json_encode($data[$key] ?? [], JSON_UNESCAPED_SLASHES), 'type'=>'json', 'is_secret'=>false]);
            }

            try { $audit->record('admin.system_settings.updated', null, ['setting_keys'=>self::KEYS], $request); } catch (\Throwable $auditException) { report($auditException); }
            return back()->with('success','System settings saved.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error','System settings could not be saved safely.');
        }
    }
}
