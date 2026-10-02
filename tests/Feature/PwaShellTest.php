<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaShellTest extends TestCase
{
    public function test_root_html_contains_valid_pwa_references_without_literal_escape_sequences(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('rel="manifest" href="/manifest.webmanifest"', false)
            ->assertSee('name="theme-color" content="#4338ca"', false)
            ->assertSee('href="/icons/semizzy-one.svg"', false)
            ->assertDontSee('\\n', false);
    }

    public function test_pwa_manifest_and_offline_fallback_are_available(): void
    {
        $this->get('/manifest.webmanifest')
            ->assertOk()
            ->assertHeader('content-type', 'application/manifest+json')
            ->assertSee('"display": "standalone"', false);

        $this->get('/offline.html')
            ->assertOk()
            ->assertSee('You’re offline');
    }
}
