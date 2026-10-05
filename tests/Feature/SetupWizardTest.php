<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['semizzy.testing_installed' => false]);
    }

    public function test_setup_page_is_available_before_initial_admin_creation(): void
    {
        $response = $this->get(route('setup'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Setup/Index')
            ->where('checks.database', true)
            ->where('checks.app_key', true)
            ->where('migrations_table', true)
        );
    }

    public function test_setup_creates_only_one_initial_admin_and_then_locks(): void
    {
        $response = $this->post(route('setup.admin'), [
            'name' => 'Setup Administrator',
            'email' => 'setup-admin@example.test',
            'password' => 'Strong-Password-123!',
            'password_confirmation' => 'Strong-Password-123!',
        ]);

        $response->assertRedirect(route('admin.login'));
        $this->assertDatabaseHas('users', [
            'email' => 'setup-admin@example.test',
            'role' => 'ADMIN',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'key' => 'core.initial_admin_created',
            'value' => '1',
        ]);

        $this->get(route('setup'))->assertNotFound();

        $this->post(route('setup.admin'), [
            'name' => 'Second Administrator',
            'email' => 'second-admin@example.test',
            'password' => 'Strong-Password-123!',
            'password_confirmation' => 'Strong-Password-123!',
        ])->assertNotFound();

        $this->assertSame(1, User::query()->where('role', 'ADMIN')->count());
    }

    public function test_setup_controller_refuses_initial_admin_creation_without_app_key(): void
    {
        config(['app.key' => null]);

        $request = request()->create(route('setup.admin'), 'POST', [
            'name' => 'Setup Administrator',
            'email' => 'setup-admin@example.test',
            'password' => 'Strong-Password-123!',
            'password_confirmation' => 'Strong-Password-123!',
        ]);

        $response = app(\App\Http\Controllers\SetupController::class)->createAdmin($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(url()->previous(), $response->headers->get('Location'));
        $this->assertDatabaseMissing('users', ['email' => 'setup-admin@example.test']);
    }
}
