<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Most feature tests exercise installed-app behavior. Installation
        // tests explicitly opt out so they can exercise the real guard.
        config(['semizzy.testing_installed' => true]);
    }

    /**
     * Simulate a successfully authenticated device session for feature tests.
     * Tests specifically exercising missing/invalid device sessions can clear
     * these session values after calling actingAs().
     */
    public function actingAs($user, $guard = null)
    {
        parent::actingAs($user, $guard);

        if ($user instanceof User && $user->exists && Schema::hasTable('user_devices')) {
            $token = Str::random(64);
            $deviceKey = hash('sha256', Str::random(64));
            $device = $user->devices()->create([
                'device_key' => $deviceKey,
                'name' => 'Feature Test Device',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'SEMIZZY-ONE-Feature-Tests',
                'last_seen_at' => now(),
                'authenticated_at' => now(),
                'auth_method' => 'test',
                'session_token_hash' => Hash::make($token),
            ]);

            $this->withSession([
                'device_id' => $device->id,
                'device_session_token' => $token,
                'device_key' => $deviceKey,
            ]);
        }

        return $this;
    }
}
