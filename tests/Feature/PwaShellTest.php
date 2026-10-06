<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaShellTest extends TestCase
{
    public function test_root_html_contains_valid_pwa_references_without_literal_escape_sequences(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
            ->assertSee('name="theme-color" content="#4338ca"', false)
            ->assertSee('href="/icons/semizzy-one.svg"', false)
            ->assertDontSee('\\n', false);
    }

    public function test_pwa_manifest_and_offline_fallback_files_are_valid(): void
    {
        $manifestResponse = $this->get('/manifest.webmanifest')->assertOk();
        $manifest = json_decode($manifestResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $offlinePath = public_path('offline.html');
        $serviceWorkerPath = public_path('sw.js');

        $this->assertFileExists($offlinePath);
        $this->assertFileExists($serviceWorkerPath);

        $this->assertSame('SEMIZZY ONE', $manifest['name'] ?? null);
        $this->assertSame('standalone', $manifest['display'] ?? null);
        $this->assertSame('/dashboard', $manifest['start_url'] ?? null);
        $this->assertStringContainsString('You’re offline', (string) file_get_contents($offlinePath));
        $this->assertStringContainsString('request.mode === \'navigate\'', (string) file_get_contents($serviceWorkerPath));
    }
}
