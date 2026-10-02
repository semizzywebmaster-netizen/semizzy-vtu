<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UpdatePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_change_password(): void
    {
        $user = User::create([
            'name' => 'Password User',
            'email' => 'password@example.test',
            'password' => 'Old-Strong-Password-123!',
            'role' => 'USER',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->post('/profile/password', [
                'current_password' => 'Old-Strong-Password-123!',
                'password' => 'New-Strong-Password-456!',
                'password_confirmation' => 'New-Strong-Password-456!',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Your password has been changed.');

        $fresh = $user->fresh();

        $this->assertTrue(Hash::check('New-Strong-Password-456!', $fresh->password));
        $this->assertNotSame('', (string) $fresh->remember_token);
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => 'password.changed',
        ]);
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::create([
            'name' => 'Password User',
            'email' => 'password2@example.test',
            'password' => 'Old-Strong-Password-123!',
            'role' => 'USER',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->post('/profile/password', [
                'current_password' => 'Wrong-Password-123!',
                'password' => 'New-Strong-Password-456!',
                'password_confirmation' => 'New-Strong-Password-456!',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('Old-Strong-Password-123!', $user->fresh()->password));
        $this->assertDatabaseMissing('security_events', [
            'user_id' => $user->id,
            'event' => 'password.changed',
        ]);
    }
}
