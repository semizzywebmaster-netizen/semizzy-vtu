<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

class SemizzyCreateAdmin extends Command
{
    protected $signature = 'semizzy:admin:create';

    protected $description = 'Create the first SEMIZZY ONE administrator from the server CLI';

    public function handle(): int
    {
        try {
            if (! Schema::hasTable('users') || ! Schema::hasTable('system_settings')) {
                $this->error('The database schema is not ready. Run php artisan migrate first.');
                return self::FAILURE;
            }

            if (User::query()->where('role', 'ADMIN')->exists()) {
                $this->error('An administrator already exists. The initial administrator command is one-time only.');
                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            report($e);
            $this->error('The database could not be checked. Verify the database configuration and run the command again.');
            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Administrator name'));
        $email = strtolower(trim((string) $this->ask('Administrator email')));
        $password = (string) $this->secret('Administrator password');
        $confirmation = (string) $this->secret('Confirm administrator password');

        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $confirmation,
            ],
            [
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email', 'max:190', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->letters()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($name, $email, $password): void {
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

                if (! $marker || $marker->value === '1' || User::query()->where('role', 'ADMIN')->lockForUpdate()->exists()) {
                    throw new \RuntimeException('Initial administrator already exists.');
                }

                $admin = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => 'ADMIN',
                'status' => 'active',
                ]);

                    $admin->forceFill(['email_verified_at' => now()])->save();

                DB::table('system_settings')
                ->where('key', 'core.initial_admin_created')
                    ->update([
                        'value' => '1',
                        'updated_at' => now(),
                    ]);
            });
        } catch (\Throwable $e) {
            report($e);
            $this->error('The administrator could not be created. No partial bootstrap changes were committed. Check the application logs.');
            return self::FAILURE;
        }

        $this->info('Initial administrator created successfully.');
        $this->line('Email: '.$email);
        $this->line('Email verification is marked complete for the bootstrap account.');

        return self::SUCCESS;
    }
}
