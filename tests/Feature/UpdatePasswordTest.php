<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdatePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_change_requires_otp(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/profile/password', [
                'current_password' => 'not-submitted',
                'password' => 'not-submitted',
                'password_confirmation' => 'not-submitted',
            ])
            ->assertSessionHasErrors(['current_password', 'password', 'otp_code']);
    }
}
