<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UserDeviceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_have_phone_verification_fields_and_device_records_are_user_scoped(): void
    {
        $user = User::factory()->create([
            'phone' => '+2348012345678',
        ]);

        $device = UserDevice::create([
            'user_id' => $user->id,
            'device_key' => hash('sha256', 'device-1'),
            'name' => 'Android',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'TestAgent',
            'last_seen_at' => Carbon::now(),
        ]);

        $this->assertSame('+2348012345678', $user->fresh()->phone);
        $this->assertNull($user->fresh()->phone_verified_at);
        $this->assertTrue($device->fresh()->user->is($user));
        $this->assertNull($device->fresh()->revoked_at);
    }
}
