<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_site_identity_is_shared_and_published_to_pwa_manifest(): void
    {
        SystemSetting::create([
            'key' => 'platform_name',
            'value' => 'My Platform',
            'type' => 'string',
            'is_secret' => false,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Welcome')
                ->where('platform.platform_name', 'My Platform'));

        $this->get('/manifest.webmanifest')
            ->assertOk()
            ->assertJsonPath('name', 'My Platform')
            ->assertJsonPath('short_name', 'My Platform')
            ->assertJsonPath('description', 'My Platform platform');
    }
}
