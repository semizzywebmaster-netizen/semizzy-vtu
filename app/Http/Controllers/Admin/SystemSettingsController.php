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
    private const KEYS = ['platform_name', 'support_email', 'support_notice', 'default_timezone'];

    public function index(): Response
    {
        $stored = SystemSetting::query()->whereIn('key', self::KEYS)->pluck('value', 'key');

        return Inertia::render('Admin/Settings', [
            'settings' => [
                'platform_name' => (string) ($stored['platform_name'] ?? config('app.name', 'SEMIZZY ONE')),
                'support_email' => (string) ($stored['support_email'] ?? config('mail.from.address', '')),
                'support_notice' => (string) ($stored['support_notice'] ?? ''),
                'default_timezone' => (string) ($stored['default_timezone'] ?? config('app.timezone', 'UTC')),
            ],
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'platform_name' => ['required', 'string', 'min:2', 'max:80'],
            'support_email' => ['nullable', 'email', 'max:254'],
            'support_notice' => ['nullable', 'string', 'max:500'],
            'default_timezone' => ['required', 'timezone'],
        ]);

        $types = [
            'platform_name' => 'string',
            'support_email' => 'string',
            'support_notice' => 'string',
            'default_timezone' => 'string',
        ];

        foreach (self::KEYS as $key) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $data[$key] ?? null, 'type' => $types[$key], 'is_secret' => false],
            );
        }

        $audit->record('admin.system_settings.updated', null, ['setting_keys' => self::KEYS], $request);

        return back()->with('success', 'System settings saved.');
    }
}
