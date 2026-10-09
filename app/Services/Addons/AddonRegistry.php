<?php

namespace App\Services\Addons;

use Illuminate\Validation\ValidationException;

class AddonRegistry
{
    public function all(): array
    {
        $root = realpath(base_path('addons'));
        if ($root === false || !is_dir($root)) return [];

        // Keep legacy flat addons working while allowing addons/<category>/<addon>/manifest.php.
        $files = array_merge(
            glob($root . '/*/manifest.php') ?: [],
            glob($root . '/*/*/manifest.php') ?: [],
        );
        sort($files, SORT_STRING);

        $manifests = [];
        $seen = [];

        foreach ($files as $file) {
            try {
                $realFile = realpath($file);
                if ($realFile === false || !$this->isWithinRoot($realFile, $root)) {
                    report(new \RuntimeException('Addon manifest resolves outside the addons directory.'));
                    continue;
                }

                $manifest = require $realFile;
                if (!is_array($manifest)) continue;
                $this->validate($manifest);

                $identifier = strtolower(trim($manifest['identifier']));
                if (isset($seen[$identifier])) {
                    report(new \RuntimeException(
                        "Duplicate addon identifier [{$identifier}] found at [{$realFile}]; keeping [{$seen[$identifier]}]."
                    ));
                    continue;
                }

                $directory = realpath(dirname($realFile));
                if ($directory === false || !$this->isWithinRoot($directory, $root)) {
                    report(new \RuntimeException('Addon source directory resolves outside the addons directory.'));
                    continue;
                }

                $relativePath = str_replace('\\', '/', ltrim(substr($directory, strlen($root)), DIRECTORY_SEPARATOR));
                if ($relativePath === '' || str_contains('/' . $relativePath . '/', '/../')) {
                    report(new \RuntimeException('Addon source path is invalid.'));
                    continue;
                }

                // "source" remains the legacy basename for callers that rely on it.
                // New path-aware code must use source_path for nested addon directories.
                $manifest['source'] = basename($directory);
                $manifest['source_path'] = $relativePath;
                $manifests[$identifier] = $manifest;
                $seen[$identifier] = $realFile;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        ksort($manifests);
        return array_values($manifests);
    }

    public function registerAutoloaders(): void
    {
        foreach ($this->all() as $manifest) {
            $prefix = trim((string) ($manifest['autoload_namespace'] ?? ''), '\\') . '\\';
            $sourcePath = (string) ($manifest['source_path'] ?? $manifest['source'] ?? '');
            if ($prefix === '\\' || $sourcePath === '') continue;

            // The registry only supplies normalized relative paths from within addons/.
            $base = base_path('addons/' . $sourcePath . '/src/');
            $realBase = realpath($base);
            $root = realpath(base_path('addons'));
            if ($realBase === false || $root === false || !$this->isWithinRoot($realBase, $root)) continue;
            $base = rtrim($realBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

            spl_autoload_register(static function (string $class) use ($prefix, $base): void {
                if (!str_starts_with($class, $prefix)) return;
                $relative = substr($class, strlen($prefix));
                $file = $base . str_replace('\\', '/', $relative) . '.php';
                if (is_file($file)) require_once $file;
            }, true, true);
        }
    }

    public function commercialAdapters(): array
    {
        $adapters = [];
        foreach ($this->all() as $manifest) {
            $classes = $manifest['commercial_adapters'] ?? [];
            if (!is_array($classes)) continue;

            foreach ($classes as $key => $definition) {
                if (is_string($definition)) {
                    $adapters[] = [
                        'class' => $definition,
                        'priority' => 100,
                        'service_keys' => [],
                        'addon' => $manifest['identifier'],
                    ];
                    continue;
                }

                if (!is_array($definition) || empty($definition['class'])) continue;
                $adapters[] = [
                    'class' => (string) $definition['class'],
                    'priority' => (int) ($definition['priority'] ?? 100),
                    'service_keys' => is_array($definition['service_keys'] ?? null) ? $definition['service_keys'] : [],
                    'addon' => $manifest['identifier'],
                ];
            }
        }

        return array_values(array_filter($adapters, static fn (array $item): bool => class_exists($item['class'])));
    }

    public function find(string $identifier): ?array
    {
        $identifier = strtolower(trim($identifier));
        foreach ($this->all() as $manifest) {
            if (strtolower($manifest['identifier']) === $identifier) return $manifest;
        }
        return null;
    }

    public function require(string $identifier): array
    {
        $manifest = $this->find($identifier);
        if (!$manifest) {
            throw ValidationException::withMessages([
                'identifier' => "Addon [{$identifier}] is not available in the installed addon registry.",
            ]);
        }
        return $manifest;
    }

    private function isWithinRoot(string $path, string $root): bool
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with($path . (is_dir($path) ? DIRECTORY_SEPARATOR : ''), $root)
            || $path === rtrim($root, DIRECTORY_SEPARATOR);
    }

    private function validate(array $manifest): void
    {
        foreach (['identifier','name','version'] as $key) {
            if (!isset($manifest[$key]) || !is_string($manifest[$key]) || trim($manifest[$key]) === '') {
                throw ValidationException::withMessages(['manifest' => "Addon manifest field [{$key}] is required."]);
            }
        }
    }
}
