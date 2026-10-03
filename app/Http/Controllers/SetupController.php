<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class SetupController extends Controller
{
    private function databaseAvailable(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function locked(): bool
    {
        if (! $this->databaseAvailable()) {
            return false;
        }

        try {
            if (! Schema::hasTable('system_settings')) {
                return Schema::hasTable('users') && User::query()->exists();
            }

            return DB::table('system_settings')->where('key', 'core.initial_admin_created')->value('value') === '1'
                || User::query()->where('role', 'ADMIN')->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    public function index()
    {
        if ($this->locked()) {
            abort(404);
        }

        $checks = [
            'php' => version_compare(PHP_VERSION, '8.4.0', '>='),
            'pdo' => extension_loaded('pdo'),
            'mbstring' => extension_loaded('mbstring'),
            'openssl' => extension_loaded('openssl'),
            'json' => extension_loaded('json'),
            'pdo_mysql' => DB::getDriverName() !== 'mysql' || extension_loaded('pdo_mysql'),
            'app_key' => filled(config('app.key')),
            'app_url' => filter_var(config('app.url'), FILTER_VALIDATE_URL) !== false,
            'storage' => is_writable(storage_path()),
            'bootstrap_cache' => is_writable(base_path('bootstrap/cache')),
            'database' => false,
        ];

        try {
            DB::connection()->getPdo();
            $checks['database'] = true;
        } catch (\Throwable) {
            // Reported in the wizard without exposing connection details.
        }

        return Inertia::render('Setup/Index', [
            'checks' => $checks,
            'migrations_table' => Schema::hasTable('migrations'),
            'app_url' => config('app.url'),
        ]);
    }

    public function generateKey()
    {
        abort_if($this->locked(), 404);

        if (filled(config('app.key'))) {
            return back()->with('success', 'Application key is already configured.');
        }

        if (! is_file(base_path('.env')) || ! is_writable(base_path('.env'))) {
            return back()->withErrors(['setup' => 'The .env file is missing or not writable. Generate APP_KEY from the server environment, then refresh this page.']);
        }

        try {
            Artisan::call('key:generate', ['--force' => true]);
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['setup' => 'The application key could not be generated. Check the server environment and application logs.']);
        }

        return back()->with('success', 'Application key generated successfully. Refreshing the setup checks is safe.');
    }

    public function migrate(Request $request)
    {
        abort_if($this->locked(), 404);

        $request->validate(['confirm' => ['required', 'accepted']]);

        try {
            if (! $this->databaseAvailable()) {
                throw new \RuntimeException('Database is unavailable.');
            }
            Artisan::call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['setup' => 'Database setup could not be completed. Check the database configuration and application logs.']);
        }

        return redirect()->route('setup')->with('success', 'Database migrations completed. You can now create the initial administrator.');
    }

    public function createAdmin(Request $request)
    {
        abort_if($this->locked(), 404);

        if (! $this->databaseAvailable() || ! Schema::hasTable('users') || ! Schema::hasTable('system_settings')) {
            return back()->withErrors(['setup' => 'Complete the database migration step before creating the administrator.']);
        }

        if (! filled(config('app.key'))) {
            return back()->withErrors(['setup' => 'APP_KEY is missing. Configure it before creating the administrator.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->letters()],
        ]);

        DB::transaction(function () use ($data): void {
            DB::table('system_settings')->insertOrIgnore([
                'key' => 'core.initial_admin_created',
                'value' => '0',
                'type' => 'boolean',
                'is_secret' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $marker = DB::table('system_settings')->where('key', 'core.initial_admin_created')->lockForUpdate()->first();
            if (!$marker || $marker->value === '1' || User::query()->where('role', 'ADMIN')->exists()) {
                abort(409, 'Initial administrator already exists.');
            }

            $admin = User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'password' => $data['password'],
                'role' => 'ADMIN',
                'status' => 'active',
            ]);
            $admin->forceFill(['email_verified_at' => now()])->save();

            DB::table('system_settings')->where('key', 'core.initial_admin_created')->update([
                'value' => '1',
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('admin.login')->with('success', 'Setup completed. You can now sign in as administrator.');
    }
}
