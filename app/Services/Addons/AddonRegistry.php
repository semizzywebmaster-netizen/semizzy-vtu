<?php

namespace App\Services\Addons;

use Illuminate\Validation\ValidationException;

class AddonRegistry
{
    public function all(): array
    {
        $root = base_path('addons');
        if (!is_dir($root)) return [];

        $manifests = [];
        foreach (glob($root . '/*/manifest.php') ?: [] as $file) {
            try {
                $manifest = require $file;
                if (!is_array($manifest)) continue;
                $manifest['compatibility'] ??= $manifest['core_compatibility'] ?? null;
                $this->validate($manifest);
                $manifest['source'] = basename(dirname($file));
                $manifests[$manifest['identifier']] = $manifest;
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
            $source = (string) ($manifest['source'] ?? '');
            if ($prefix === '\\' || $source === '') continue;

            $base = base_path('addons/' . $source . '/src/');
            if (!is_dir($base)) continue;

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

    private function validate(array $manifest): void
    {
        foreach (['identifier','name','version'] as $key) {
            if (!isset($manifest[$key]) || !is_string($manifest[$key]) || trim($manifest[$key]) === '') {
                throw ValidationException::withMessages(['manifest' => "Addon manifest field [{$key}] is required."]);
            }
        }
    }
}
