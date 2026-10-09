<?php

namespace App\Services\Addons;

use Illuminate\Support\Facades\Route;
use RuntimeException;

class AddonRouteRegistrar
{
    public function __construct(private readonly AddonRegistry $registry)
    {
    }

    public function registerWebRoutes(): void
    {
        $this->registerFiles('web_route_files');
    }

    public function registerApiRoutes(): void
    {
        $this->registerFiles('api_route_files');
    }

    private function registerFiles(string $manifestKey): void
    {
        foreach ($this->registry->all() as $manifest) {
            $files = $manifest[$manifestKey] ?? [];
            if (!is_array($files)) {
                continue;
            }

            foreach ($files as $file) {
                if (!is_string($file) || trim($file) === '') {
                    throw new RuntimeException("Invalid addon route file declaration for [{$manifest['identifier']}].");
                }

                $relative = ltrim(str_replace('\\', '/', $file), '/');
                if (str_contains($relative, '..') || !str_starts_with($relative, 'addons/')) {
                    throw new RuntimeException("Unsafe addon route file path [{$file}].");
                }

                $path = base_path($relative);
                if (!is_file($path)) {
                    throw new RuntimeException("Addon route file not found [{$relative}].");
                }

                // Apply the lifecycle guard centrally so every HTTP route declared
                // by an addon remains unavailable until that addon is active.
                // Route files may add stricter middleware, but cannot accidentally
                // omit the Core activation check.
                Route::middleware('ensure.addon:'.(string) $manifest['identifier'])
                    ->group(function () use ($path): void {
                        require $path;
                    });
            }
        }
    }
}
