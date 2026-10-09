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
        $root = realpath(base_path('addons'));
        if ($root === false || !is_dir($root)) return;

        foreach ($this->registry->all() as $manifest) {
            $files = $manifest[$manifestKey] ?? [];
            if (!is_array($files)) continue;

            foreach ($files as $file) {
                if (!is_string($file) || trim($file) === '') {
                    throw new RuntimeException("Invalid addon route file declaration for [{$manifest['identifier']}].");
                }

                $relative = str_replace('\\', '/', trim($file));
                if (str_starts_with($relative, '/') || preg_match('/^[A-Za-z]:/', $relative)) {
                    throw new RuntimeException("Unsafe addon route file path [{$file}].");
                }

                $segments = explode('/', $relative);
                if (in_array('..', $segments, true) || !str_starts_with($relative, 'addons/')) {
                    throw new RuntimeException("Unsafe addon route file path [{$file}].");
                }

                $candidates = [base_path($relative)];

                // If a legacy manifest path points at the old flat folder, remap its suffix
                // to the discovered physical source path without changing the public addon ID.
                $source = (string) ($manifest['source'] ?? '');
                $sourcePath = (string) ($manifest['source_path'] ?? $source);
                $legacyPrefix = 'addons/' . $source . '/';
                if ($source !== '' && $sourcePath !== '' && str_starts_with($relative, $legacyPrefix)) {
                    $suffix = substr($relative, strlen($legacyPrefix));
                    $candidates[] = base_path('addons/' . $sourcePath . '/' . $suffix);
                }

                $resolved = null;
                foreach ($candidates as $candidate) {
                    $real = realpath($candidate);
                    if ($real !== false && is_file($real) && $this->isWithinRoot($real, $root)) {
                        $resolved = $real;
                        break;
                    }
                }

                if ($resolved === null) {
                    throw new RuntimeException("Addon route file not found or resolves outside addon root [{$relative}].");
                }

                require $resolved;
            }
        }
    }

    private function isWithinRoot(string $path, string $root): bool
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with($path, $root);
    }
}
