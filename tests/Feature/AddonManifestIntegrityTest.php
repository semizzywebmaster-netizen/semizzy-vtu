<?php

namespace Tests\Feature;

use Tests\TestCase;

class AddonManifestIntegrityTest extends TestCase
{
    public function test_addon_manifests_are_unique_and_reference_existing_files(): void
    {
        $manifestFiles = glob(base_path('addons/*/manifest.php')) ?: [];
        $this->assertNotEmpty($manifestFiles, 'No addon manifests were found.');

        $identifiers = [];
        $errors = [];

        foreach ($manifestFiles as $manifestFile) {
            $source = basename(dirname($manifestFile));

            try {
                $manifest = require $manifestFile;
            } catch (\Throwable $exception) {
                $errors[] = "{$source}: manifest could not be loaded ({$exception->getMessage()})";
                continue;
            }

            if (!is_array($manifest)) {
                $errors[] = "{$source}: manifest must return an array";
                continue;
            }

            $identifier = $manifest['identifier'] ?? null;
            if (!is_string($identifier) || trim($identifier) === '') {
                $errors[] = "{$source}: missing non-empty identifier";
            } elseif (isset($identifiers[$identifier])) {
                $errors[] = "{$source}: duplicate identifier [{$identifier}] also used by [{$identifiers[$identifier]}]";
            } else {
                $identifiers[$identifier] = $source;
            }

            if (!empty($manifest['autoload_namespace']) && !is_dir(base_path("addons/{$source}/src"))) {
                $errors[] = "{$source}: autoload_namespace is set but addons/{$source}/src is missing";
            }

            foreach ((array) ($manifest['migrations'] ?? []) as $migration) {
                if (!is_string($migration) || $migration === '') {
                    $errors[] = "{$source}: migration entries must be non-empty filenames";
                    continue;
                }

                $migrationPath = base_path("addons/{$source}/database/migrations/{$migration}");
                if (!is_file($migrationPath)) {
                    $errors[] = "{$source}: declared migration [{$migration}] was not found at addons/{$source}/database/migrations/";
                }
            }

            foreach (['web_route_files', 'api_route_files'] as $routeKey) {
                foreach ((array) ($manifest[$routeKey] ?? []) as $routeFile) {
                    if (!is_string($routeFile) || $routeFile === '' || !is_file(base_path($routeFile))) {
                        $errors[] = "{$source}: declared {$routeKey} file [".(is_scalar($routeFile) ? (string) $routeFile : 'invalid')."] does not exist";
                    }
                }
            }
        }

        $this->assertSame([], $errors, "Addon manifest integrity errors:\n".implode("\n", $errors));
    }
}
