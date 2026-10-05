<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateInitialAdmin extends Command
{
    protected $signature = 'semizzy:admin:create';

    protected $description = 'Safely create the first SEMIZZY ONE administrator from the command line';

    public function handle(): int
    {
        $marker = DB::table('system_settings')
            ->where('key', 'core.initial_admin_created')
            ->value('value');

        if ($marker === '1' || User::query()->where('role', 'ADMIN')->exists()) {
            $this->error('An initial administrator has already been created. Use the existing admin account or follow the documented recovery procedure.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Administrator name'));
        $email = strtolower(trim((string) $this->ask('Administrator email')));
        $password = (string) $this->secret('Administrator password');
        $confirmation = (string) $this->secret('Confirm administrator password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->letters()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        try {
            $admin = DB::transaction(function () use ($name, $email, $password): User {
                DB::table('system_settings')->insertOrIgnore([
                    'key' => 'core.initial_admin_created',
                    'value' => '0',
                    'type' => 'boolean',
                    'is_secret' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $marker = DB::table('system_settings')
                    ->where('key', 'core.initial_admin_created')
                    ->lockForUpdate()
                    ->first();

                if (! $marker || $marker->value === '1' || User::query()->where('role', 'ADMIN')->exists()) {
                    throw new \RuntimeException('An initial administrator has already been created. Use the existing admin account or follow the documented recovery procedure.');
                }

                $admin = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'role' => 'ADMIN',
                    'status' => 'active',
                ]);
                $admin->forceFill(['email_verified_at' => now()])->save();

                DB::table('system_settings')
                    ->where('key', 'core.initial_admin_created')
                    ->update(['value' => '1', 'updated_at' => now()]);

                return $admin;
            });
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Initial administrator created successfully.');
        $this->line('Email: '.$admin->email);
        $adminLoginPath = trim((string) config('semizzy.admin_login_path', 'admin/login'), '/') ?: 'admin/login';
        $this->line('Sign in at: '.url('/'.$adminLoginPath));

        return self::SUCCESS;
    }
}
