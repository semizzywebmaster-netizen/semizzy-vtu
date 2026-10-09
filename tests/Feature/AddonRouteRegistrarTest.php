<?php

namespace Tests\Feature;

use App\Services\Addons\AddonRegistry;
use App\Services\Addons\AddonRouteRegistrar;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AddonRouteRegistrarTest extends TestCase
{
    private string $addonDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->addonDirectory = base_path('addons/testing-category/route-fixture');
        if (!is_dir($this->addonDirectory . '/routes')) {
            mkdir($this->addonDirectory . '/routes', 0775, true);
        }
        file_put_contents(
            $this->addonDirectory . '/routes/web.php',
            "<?php\n\\Illuminate\\Support\\Facades\\Route::get('/__nested_addon_route_fixture', fn () => 'ok');\n"
        );
    }

    protected function tearDown(): void
    {
        $routeFile = $this->addonDirectory . '/routes/web.php';
        if (is_file($routeFile)) unlink($routeFile);
        $routesDirectory = $this->addonDirectory . '/routes';
        if (is_dir($routesDirectory) && count(scandir($routesDirectory) ?: []) === 2) rmdir($routesDirectory);
        if (is_dir($this->addonDirectory) && count(scandir($this->addonDirectory) ?: []) === 2) rmdir($this->addonDirectory);
        $categoryDirectory = dirname($this->addonDirectory);
        if (is_dir($categoryDirectory) && count(scandir($categoryDirectory) ?: []) === 2) rmdir($categoryDirectory);

        parent::tearDown();
    }

    public function test_legacy_route_declaration_resolves_to_categorized_source_path(): void
    {
        $registry = $this->createMock(AddonRegistry::class);
        $registry->method('all')->willReturn([[
            'identifier' => 'test.nested-route-fixture',
            'source' => 'route-fixture',
            'source_path' => 'testing-category/route-fixture',
            'web_route_files' => ['addons/route-fixture/routes/web.php'],
        ]]);

        (new AddonRouteRegistrar($registry))->registerWebRoutes();

        $this->assertNotNull(Route::getRoutes()->match(
            \Illuminate\Http\Request::create('/__nested_addon_route_fixture', 'GET')
        ));
    }

    public function test_route_path_traversal_is_rejected(): void
    {
        $registry = $this->createMock(AddonRegistry::class);
        $registry->method('all')->willReturn([[
            'identifier' => 'test.unsafe-route-fixture',
            'source' => 'route-fixture',
            'source_path' => 'testing-category/route-fixture',
            'web_route_files' => ['addons/../outside/routes/web.php'],
        ]]);

        $this->expectException(\RuntimeException::class);
        (new AddonRouteRegistrar($registry))->registerWebRoutes();
    }
}
