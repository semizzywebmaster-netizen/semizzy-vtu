<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SemizzyCreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_admin_command_creates_verified_admin_and_locks_bootstrap_marker(): void
    {
        $this->artisan('semizzy:admin:create')
            ->expectsQuestion('Administrator name', 'CLI Admin')
            ->expectsQuestion('Administrator email', 'CLI.ADMIN@example.com')
            ->expectsQuestion('Administrator password', 'StrongPassword123!')
            ->expectsQuestion('Confirm administrator password', 'StrongPassword123!')
            ->assertExitCode(0);

        $admin = User::query()->where('email', 'cli.admin@example.com')->first();

        $this->assertNotNull($admin);
        $this->assertSame('ADMIN', $admin->role);
        $this->assertSame('active', $admin->status);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertSame('1', DB::table('system_settings')->where('key', 'core.initial_admin_created')->value('value'));
    }

    public function test_initial_admin_command_refuses_to_create_a_second_admin(): void
    {
        User::create([
            'name' => 'Existing Admin',
            'email' => 'existing-admin@example.com',
            'password' => 'StrongPassword123!',
            'role' => 'ADMIN',
            'status' => 'active',
        ]);

        $this->artisan('semizzy:admin:create')
            ->expectsOutputToContain('An initial administrator has already been created')
            ->assertExitCode(1);

        $this->assertSame(1, User::query()->where('role', 'ADMIN')->count());
    }
}
