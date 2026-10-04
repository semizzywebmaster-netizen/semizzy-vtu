<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureApplicationInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $installed = $this->isInstalled();

        // A fresh installation cannot use database-backed sessions/cache
        // because their tables do not exist until the installer runs the
        // initial migrations. This middleware is prepended to the web group,
        // so these temporary drivers are selected before Laravel's StartSession
        // middleware executes.
        if (! $installed) {
            config([
                'session.driver' => 'file',
                'cache.default' => 'file',
            ]);

            // The installer must always remain reachable while the
            // application is uninstalled or partially installed.
            if ($request->is('setup') || $request->is('setup/*')) {
                return $next($request);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'SEMIZZY ONE has not been installed.',
                    'install_url' => route('setup'),
                ], 503);
            }

            return redirect()->route('setup');
        }

        return $next($request);
    }

    private function isInstalled(): bool
    {
        try {
            if (! Schema::hasTable('system_settings') || ! Schema::hasTable('users')) {
                return false;
            }

            $marker = DB::table('system_settings')
                ->where('key', 'core.initial_admin_created')
                ->value('value');

            if ((string) $marker !== '1') {
                return false;
            }

            return DB::table('users')
                ->where('role', 'ADMIN')
                ->exists();
        } catch (\Throwable) {
            // A missing/inaccessible database is an uninstalled state from
            // the application's perspective. The setup wizard will explain
            // the database problem to the administrator.
            return false;
        }
    }
}
