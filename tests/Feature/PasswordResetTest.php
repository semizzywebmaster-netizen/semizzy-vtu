<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_recovery_page_is_available(): void
    {
        $this->get('/forgot-password')->assertOk();
    }

    public function test_password_recovery_request_uses_generic_otp_response(): void
    {
        Mail::fake();

        User::create([
            'name' => 'Recovery User',
            'email' => fake()->unique()->safeEmail(),
            'password' => fake()->password(20, 30, true, true, true),
            'role' => 'USER',
            'status' => 'active',
        ]);

        $this->post('/forgot-password/otp', [
            'email' => fake()->safeEmail(),
            'otp_channel' => 'email',
        ])->assertSessionHas('otp_sent');
    }

    public function test_password_recovery_requires_a_six_digit_otp(): void
    {
        $response = $this->post('/forgot-password/reset', [
            'email' => fake()->safeEmail(),
            'otp_code' => '123',
            'password' => fake()->password(20, 30, true, true, true),
            'password_confirmation' => fake()->password(20, 30, true, true, true),
        ]);

        $response->assertSessionHasErrors(['otp_code', 'password']);
    }
}
