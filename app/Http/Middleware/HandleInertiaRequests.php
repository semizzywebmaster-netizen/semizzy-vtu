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
            ],
        ]);
    }
}
