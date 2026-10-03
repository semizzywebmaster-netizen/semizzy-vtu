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
    private function locked(): bool
    {
        if (! Schema::hasTable('system_settings')) {
            return Schema::hasTable('users') && User::query()->exists();
        }

        return DB::table('system_settings')->where('key', 'core.initial_admin_created')->value('value') === '1'
            || User::query()->where('role', 'ADMIN')->exists();
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

    public function migrate(Request $request)
    {
        abort_if($this->locked(), 404);

        $request->validate(['confirm' => ['required', 'accepted']]);

        try {
            DB::connection()->getPdo();
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

        if (! Schema::hasTable('users') || ! Schema::hasTable('system_settings')) {
            return back()->withErrors(['setup' => 'Run the database migrations before creating the administrator.']);
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
