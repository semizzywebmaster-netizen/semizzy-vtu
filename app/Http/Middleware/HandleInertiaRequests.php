<?php

namespace App\Http\Middleware;

use App\Services\System\SystemSettingsService;
use App\Services\System\AdminNavigationService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        if ($request->user() && $request->session()->get('device_id')) {
            $device = $request->user()->devices()->find($request->session()->get('device_id'));
            if (!$device || $device->revoked_at) {
                \Illuminate\Support\Facades\Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                ] : null,
            ],
            'platform' => fn () => app(SystemSettingsService::class)->all(),
            'navigation' => [
                'unreadNotifications' => fn () => $request->user()?->unreadNotifications()->count() ?? 0,
                'admin' => fn () => $request->user() ? [
                    'items' => app(AdminNavigationService::class)->for($request->user()),
                ] : ['items' => []],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'deviceVerificationRequired' => fn () => (bool) $request->session()->get('device_login_pending'),
            ],
        ]);
    }
}
