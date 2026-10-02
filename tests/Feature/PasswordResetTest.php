<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_a_reset_notification(): void
    {
        Notification::fake();

        User::create([
            'name' => 'Reset User',
            'email' => 'reset@example.test',
            'password' => 'Old-Strong-Password-123!',
            'role' => 'USER',
            'status' => 'active',
        ]);

        $this->post('/forgot-password', ['email' => 'reset@example.test'])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo(
            User::where('email', 'reset@example.test')->firstOrFail(),
            ResetPassword::class
        );
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();

        $user = User::create([
            'name' => 'Reset User',
            'email' => 'reset2@example.test',
            'password' => 'Old-Strong-Password-123!',
            'role' => 'USER',
            'status' => 'active',
        ]);

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();

        $notification = Notification::sent($user, ResetPassword::class)->first();
        $token = $notification->token;

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'New-Strong-Password-456!',
            'password_confirmation' => 'New-Strong-Password-456!',
        ])->assertRedirect('/login');

        $this->assertTrue(Hash::check('New-Strong-Password-456!', $user->fresh()->password));
    }

    public function test_invalid_reset_token_is_rejected(): void
    {
        $user = User::create([
            'name' => 'Reset User',
            'email' => 'reset3@example.test',
            'password' => 'Old-Strong-Password-123!',
            'role' => 'USER',
            'status' => 'active',
        ]);

        $this->post('/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'New-Strong-Password-456!',
            'password_confirmation' => 'New-Strong-Password-456!',
        ])->assertSessionHasErrors('email');
    }
}
