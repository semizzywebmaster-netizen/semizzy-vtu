<?php

namespace Tests\Feature;

use App\Services\Addons\AddonRegistry;
use Tests\TestCase;

class AddonRegistryDiscoveryTest extends TestCase
{
    private string $addonDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->addonDirectory = base_path('addons/testing-category/registry-discovery-fixture');
        if (is_dir($this->addonDirectory)) {
            $this->removeDirectory($this->addonDirectory);
        }

        mkdir($this->addonDirectory . '/src', 0775, true);
        file_put_contents($this->addonDirectory . '/manifest.php', "<?php\nreturn [\n    'identifier' => 'test.registry-discovery-fixture',\n    'name' => 'Registry Discovery Fixture',\n    'version' => '1.0.0',\n    'autoload_namespace' => 'Fixture\\\\',\n];\n");
    }

    protected function tearDown(): void
    {
        if (isset($this->addonDirectory) && is_dir($this->addonDirectory)) {
            $this->removeDirectory($this->addonDirectory);
        }

        $categoryDirectory = base_path('addons/testing-category');
        if (is_dir($categoryDirectory) && count(scandir($categoryDirectory) ?: []) === 2) {
            rmdir($categoryDirectory);
        }

        parent::tearDown();
    }

    public function test_registry_discovers_nested_addons_and_exposes_safe_relative_source_path(): void
    {
        $manifest = app(AddonRegistry::class)->find('test.registry-discovery-fixture');

        $this->assertNotNull($manifest);
        $this->assertSame('registry-discovery-fixture', $manifest['source']);
        $this->assertSame('testing-category/registry-discovery-fixture', $manifest['source_path']);
    }

    private function removeDirectory(string $directory): void
    {
        $items = scandir($directory) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($directory);
    }
}
