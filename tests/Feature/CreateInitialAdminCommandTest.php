<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateInitialAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_verified_admin_with_hashed_password(): void
    {
        config(['semizzy.admin_login_path' => 'control/sign-in']);

        $this->artisan('semizzy:admin:create')
            ->expectsQuestion('Administrator name', 'Initial Admin')
            ->expectsQuestion('Administrator email', 'initial-admin@example.test')
            ->expectsQuestion('Administrator password', 'Strong-Password-123!')
            ->expectsQuestion('Confirm administrator password', 'Strong-Password-123!')
            ->expectsOutputToContain('Initial administrator created successfully.')
            ->expectsOutputToContain(url('/control/sign-in'))
            ->assertExitCode(0);

        $admin = User::query()->where('email', 'initial-admin@example.test')->firstOrFail();
        $this->assertSame('ADMIN', $admin->role);
        $this->assertSame('active', $admin->status);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertNotSame('Strong-Password-123!', $admin->password);
        $this->assertDatabaseHas('system_settings', ['key' => 'core.initial_admin_created', 'value' => '1']);
    }

    public function test_command_refuses_to_create_a_second_initial_admin(): void
    {
        User::create([
            'name' => 'Existing Admin',
            'email' => 'existing-admin@example.test',
            'password' => 'Strong-Password-123!',
            'role' => 'ADMIN',
            'status' => 'active',
        ]);

        $this->artisan('semizzy:admin:create')
            ->expectsQuestion('Administrator name', 'Another Admin')
            ->expectsQuestion('Administrator email', 'another-admin@example.test')
            ->expectsQuestion('Administrator password', 'Strong-Password-123!')
            ->expectsQuestion('Confirm administrator password', 'Strong-Password-123!')
            ->expectsOutputToContain('An initial administrator has already been created.')
            ->assertExitCode(1);

        $this->assertSame(1, User::query()->where('role', 'ADMIN')->count());
    }
}
