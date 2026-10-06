<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'password' => 'Strong-Password-123!',
            'role' => 'USER',
            'status' => 'active',
            'tier' => 1,
            'account_type' => 'personal',
            'country' => 'Nigeria',
            'remember_token' => Str::random(10),
        ];
    }
}
