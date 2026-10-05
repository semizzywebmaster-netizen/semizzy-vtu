<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InstallationGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['semizzy.testing_installed' => false]);
    }

    public function test_fresh_application_redirects_to_setup(): void
    {
        $this->get('/')->assertRedirect(route('setup'));
        $this->get('/login')->assertRedirect(route('setup'));
    }

    public function test_setup_remains_accessible_before_installation(): void
    {
        $this->get('/setup')->assertOk();

        $this->assertSame('file', config('session.driver'));
        $this->assertSame('file', config('cache.default'));
    }

    public function test_installed_application_allows_homepage(): void
    {
        DB::table('system_settings')->insert([
            'key' => 'core.initial_admin_created',
            'value' => '1',
            'type' => 'boolean',
            'is_secret' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::create([
            'name' => 'Installation Admin',
            'email' => 'installation-admin@example.test',
            'password' => 'StrongPassword123!',
            'role' => 'ADMIN',
            'status' => 'active',
        ]);

        $this->get('/')->assertOk();
    }

    public function test_partial_installation_still_redirects_to_setup(): void
    {
        User::create([
            'name' => 'Partial Installation Admin',
            'email' => 'partial-admin@example.test',
            'password' => 'StrongPassword123!',
            'role' => 'ADMIN',
            'status' => 'active',
        ]);

        $this->get('/')->assertRedirect(route('setup'));
    }
}
