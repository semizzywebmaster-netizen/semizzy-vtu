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
        $loadedManifests = [];
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
                $loadedManifests[strtolower($identifier)] = ['source' => $source, 'manifest' => $manifest];
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
                $legacyCoreMigrationPath = base_path("database/migrations/{$migration}");

                // Some existing addon-owned schema changes are still registered
                // in Core's Laravel migration directory. Accept those legacy
                // files until the migration registry is consolidated.
                if (!is_file($migrationPath) && !is_file($legacyCoreMigrationPath)) {
                    $errors[] = "{$source}: declared migration [{$migration}] was not found in the addon or legacy Core migration directory";
                }
            }

            $declaredMigrations = array_values(array_filter((array) ($manifest['migrations'] ?? []), 'is_string'));
            foreach (glob(base_path("addons/{$source}/database/migrations/*.php")) ?: [] as $localMigrationPath) {
                $localMigration = basename($localMigrationPath);
                if (!in_array($localMigration, $declaredMigrations, true)) {
                    $errors[] = "{$source}: addon-local migration [{$localMigration}] is not declared in the manifest";
                }
            }

            $declaredPermissions = array_fill_keys(array_filter((array) ($manifest['permissions'] ?? []), 'is_string'), true);
            foreach ((array) ($manifest['role_permissions'] ?? []) as $role => $rolePermissions) {
                foreach ((array) $rolePermissions as $permission) {
                    if (!is_string($permission) || !isset($declaredPermissions[$permission])) {
                        $errors[] = "{$source}: role_permissions entry [{$role}] references undeclared permission [".(is_scalar($permission) ? (string) $permission : 'invalid')."]";
                    }
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

        foreach ($loadedManifests as $identifier => $entry) {
            $source = $entry['source'];
            $manifest = $entry['manifest'];

            foreach ((array) ($manifest['dependencies'] ?? []) as $dependency) {
                $dependencyIdentifier = is_string($dependency)
                    ? strtolower(trim($dependency))
                    : strtolower(trim((string) ($dependency['identifier'] ?? '')));
                if ($dependencyIdentifier === '' || !isset($loadedManifests[$dependencyIdentifier])) {
                    $errors[] = "{$source}: dependency [".($dependencyIdentifier !== '' ? $dependencyIdentifier : 'invalid')."] has no matching addon manifest";
                }
            }

            $declaredPermissions = array_fill_keys(array_filter((array) ($manifest['permissions'] ?? []), 'is_string'), true);
            foreach (['navigation', 'admin_navigation'] as $navigationKey) {
                foreach ((array) ($manifest[$navigationKey] ?? []) as $item) {
                    if (!is_array($item) || empty($item['permission'])) continue;
                    if (!isset($declaredPermissions[$item['permission']])) {
                        $errors[] = "{$source}: {$navigationKey} item [".(string) ($item['id'] ?? 'unknown')."] references undeclared permission [".(string) $item['permission']."]";
                    }
                }
            }
        }

        $this->assertSame([], $errors, "Addon manifest integrity errors:\n".implode("\n", $errors));
    }
}
