<?php

namespace App\Services\Addons;

use App\Models\Addon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class AddonLifecycleService
{
    public function register(array $manifest, ?int $actorId = null): Addon
    {
        $manifest = $this->validateManifest($manifest);

        return DB::transaction(function () use ($manifest, $actorId): Addon {
            $existing = Addon::withTrashed()
                ->where('identifier', $manifest['identifier'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'identifier' => 'This addon is already registered. Use the explicit update lifecycle for a new version.',
                ]);
            }

            $addon = new Addon();
            $addon->fill($this->manifestAttributes($manifest));
            $addon->status = 'draft';
            $addon->save();

            $addon->lifecycleEvents()->create([
                'addon_identifier' => $addon->identifier,
                'event' => 'registered',
                'from_status' => null,
                'to_status' => 'draft',
                'message' => 'Addon manifest registered.',
                'context' => ['version' => $addon->version],
                'actor_id' => $actorId,
            ]);

            return $addon->fresh();
        });
    }

    public function install(Addon $addon, ?int $actorId = null): Addon
    {
        $addonId = $addon->id;

        try {
            return DB::transaction(function () use ($addonId, $actorId): Addon {
                $addon = Addon::query()->lockForUpdate()->findOrFail($addonId);

                if (!in_array($addon->status, ['draft', 'failed', 'inactive'], true)) {
                    throw ValidationException::withMessages([
                        'addon' => 'Only a draft, failed, or inactive addon can begin installation.',
                    ]);
                }

                $this->transition($addon, 'validating', 'install_started', 'Addon validation started.', $actorId);
                $this->assertManifest($addon);
                $this->assertCoreCompatibility($addon->compatibility_constraint);
                $this->assertDependencies($addon->dependencies);
                $this->transition($addon, 'installing', 'installing', 'Addon installation started.', $actorId);

                $this->recordStep($addon, 'register', 'Addon registration validated.');
                $this->recordMigrationContract($addon);
                $this->recordStep($addon, 'initialize', 'Addon initialization contract validated; no addon code is executed by Core.');
                $this->recordStep($addon, 'health', 'Addon health contract validated.');

                $from = 'installing';
                $addon->update([
                    'status' => 'installed',
                    'installed_at' => now(),
                    'last_error' => null,
                ]);

                $addon->lifecycleEvents()->create([
                    'addon_identifier' => $addon->identifier,
                    'event' => 'installed',
                    'from_status' => $from,
                    'to_status' => 'installed',
                    'message' => 'Addon installed successfully.',
                    'actor_id' => $actorId,
                ]);

                return $addon->fresh();
            });
        } catch (Throwable $e) {
            $this->persistFailure($addonId, $actorId, $e, 'install_failed');
            throw $e;
        }
    }

    public function update(Addon $addon, array $manifest, ?int $actorId = null): Addon
    {
        $manifest = $this->validateManifest($manifest);

        if ($manifest['identifier'] !== $addon->identifier) {
            throw ValidationException::withMessages([
                'identifier' => 'Update manifest identifier must match the installed addon.',
            ]);
        }

        if (version_compare($manifest['version'], $addon->version, '<=')) {
            throw ValidationException::withMessages([
                'version' => "Update version {$manifest['version']} must be newer than {$addon->version}.",
            ]);
        }

        $addonId = $addon->id;

        try {
            return DB::transaction(function () use ($addonId, $manifest, $actorId): Addon {
                $addon = Addon::query()->lockForUpdate()->findOrFail($addonId);

                if (!in_array($addon->status, ['installed', 'active', 'inactive'], true)) {
                    throw ValidationException::withMessages([
                        'addon' => 'Only installed, active, or inactive addons can be updated.',
                    ]);
                }

                if (version_compare($manifest['version'], $addon->version, '<=')) {
                    throw ValidationException::withMessages([
                        'version' => "Update version {$manifest['version']} must be newer than {$addon->version}.",
                    ]);
                }

                $wasActive = $addon->status === 'active';

                $this->assertCoreCompatibility($manifest['compatibility'] ?? null);
                $this->assertDependencies($manifest['dependencies'] ?? []);
                $this->transition($addon, 'updating', 'update_started', 'Addon update started.', $actorId);
                $this->recordStep($addon, 'register', 'Update manifest validated.');
                $this->recordMigrationContract($addon, $manifest);
                $this->recordStep($addon, 'initialize', 'Addon update initialization contract validated; no addon code is executed by Core.');
                $this->recordStep($addon, 'health', 'Addon update health contract validated.');

                $from = 'updating';
                $addon->fill($this->manifestAttributes($manifest));
                $addon->status = $wasActive ? 'active' : 'installed';
                $addon->last_error = null;
                $addon->save();

                $addon->lifecycleEvents()->create([
                    'addon_identifier' => $addon->identifier,
                    'event' => 'updated',
                    'from_status' => $from,
                    'to_status' => $addon->status,
                    'message' => "Addon updated successfully to version {$addon->version}.",
                    'context' => ['version' => $addon->version],
                    'actor_id' => $actorId,
                ]);

                return $addon->fresh();
            });
        } catch (Throwable $e) {
            $this->persistFailure($addonId, $actorId, $e, 'update_failed');
            throw $e;
        }
    }

    public function canUpdate(Addon $addon, array $manifest): bool
    {
        $manifest = $this->validateManifest($manifest);

        return $manifest['identifier'] === $addon->identifier
            && version_compare($manifest['version'], $addon->version, '>');
    }

    public function activate(Addon $addon, ?int $actorId = null): Addon
    {
        return $this->transitionAndAudit($addon, 'active', 'activated', 'Addon activated.', $actorId, [
            'installed',
            'inactive',
        ]);
    }

    public function disable(Addon $addon, ?int $actorId = null): Addon
    {
        return $this->transitionAndAudit($addon, 'inactive', 'disabled', 'Addon disabled.', $actorId, [
            'active',
            'installed',
            'failed',
        ]);
    }

    public function uninstall(Addon $addon, ?int $actorId = null): Addon
    {
        return DB::transaction(function () use ($addon, $actorId): Addon {
            $addon = Addon::query()->lockForUpdate()->findOrFail($addon->id);

            if (!in_array($addon->status, ['installed', 'inactive', 'failed'], true) || !$addon->canTransitionTo('uninstalling')) {
                throw ValidationException::withMessages([
                    'addon' => 'Only an installed, inactive, or failed addon can be uninstalled.',
                ]);
            }

            $this->transition($addon, 'uninstalling', 'uninstall_started', 'Addon uninstall started.', $actorId);
            $this->recordStep($addon, 'uninstall', 'Addon uninstall contract validated; Core does not execute arbitrary addon code.');

            $from = 'uninstalling';
            $addon->update([
                'status' => 'archived',
                'activated_at' => null,
                'last_error' => null,
            ]);

            $addon->lifecycleEvents()->create([
                'addon_identifier' => $addon->identifier,
                'event' => 'uninstalled',
                'from_status' => $from,
                'to_status' => 'archived',
                'message' => 'Addon uninstalled and archived successfully.',
                'actor_id' => $actorId,
            ]);

            return $addon->fresh();
        });
    }

    public function archive(Addon $addon, ?int $actorId = null): Addon
    {
        return $this->transitionAndAudit($addon, 'archived', 'archived', 'Addon archived.', $actorId, [
            'draft',
            'installed',
            'inactive',
            'failed',
        ]);
    }

    private function manifestAttributes(array $manifest): array
    {
        return [
            'identifier' => $manifest['identifier'],
            'name' => $manifest['name'],
            'version' => $manifest['version'],
            'compatibility_constraint' => $manifest['compatibility'] ?? null,
            'dependencies' => $manifest['dependencies'] ?? [],
            'permissions' => $manifest['permissions'] ?? [],
            'navigation' => $manifest['navigation'] ?? [],
            'settings_schema' => $manifest['settings'] ?? [],
            'manifest' => $manifest,
            'package_checksum' => $manifest['checksum'] ?? null,
            'last_error' => null,
        ];
    }

    private function persistFailure(int $addonId, ?int $actorId, Throwable $exception, string $event): void
    {
        DB::transaction(function () use ($addonId, $actorId, $exception, $event): void {
            $addon = Addon::query()->lockForUpdate()->find($addonId);

            if (!$addon || $addon->status === 'archived') {
                return;
            }

            $from = $addon->status;
            $addon->update([
                'status' => 'failed',
                'last_error' => $exception->getMessage(),
            ]);

            $addon->lifecycleEvents()->create([
                'addon_identifier' => $addon->identifier,
                'event' => $event,
                'from_status' => $from,
                'to_status' => 'failed',
                'message' => 'Addon lifecycle operation failed; transactional changes were rolled back and diagnostics were persisted.',
                'context' => ['error' => $exception->getMessage()],
                'actor_id' => $actorId,
            ]);
        });
    }

    private function transitionAndAudit(
        Addon $addon,
        string $to,
        string $event,
        string $message,
        ?int $actorId,
        array $allowedFrom,
    ): Addon {
        return DB::transaction(function () use ($addon, $to, $event, $message, $actorId, $allowedFrom): Addon {
            $addon = Addon::query()->lockForUpdate()->findOrFail($addon->id);

            if (!in_array($addon->status, $allowedFrom, true) || !$addon->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'addon' => 'Addon cannot transition from its current lifecycle state.',
                ]);
            }

            $from = $addon->status;
            $addon->update([
                'status' => $to,
                'activated_at' => $to === 'active' ? now() : $addon->activated_at,
                'last_error' => $to === 'active' ? null : $addon->last_error,
            ]);

            $addon->lifecycleEvents()->create([
                'addon_identifier' => $addon->identifier,
                'event' => $event,
                'from_status' => $from,
                'to_status' => $to,
                'message' => $message,
                'actor_id' => $actorId,
            ]);

            return $addon->fresh();
        });
    }

    private function transition(Addon $addon, string $to, string $event, string $message, ?int $actorId): void
    {
        if (!$addon->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'addon' => "Invalid addon lifecycle transition: {$addon->status} to {$to}.",
            ]);
        }

        $from = $addon->status;
        $addon->update(['status' => $to]);

        $addon->lifecycleEvents()->create([
            'addon_identifier' => $addon->identifier,
            'event' => $event,
            'from_status' => $from,
            'to_status' => $to,
            'message' => $message,
            'actor_id' => $actorId,
        ]);
    }

    private function recordStep(Addon $addon, string $step, string $message): void
    {
        $addon->lifecycleEvents()->create([
            'addon_identifier' => $addon->identifier,
            'event' => 'step_' . $step,
            'from_status' => $addon->status,
            'to_status' => $addon->status,
            'message' => $message,
        ]);
    }

    private function recordMigrationContract(Addon $addon, ?array $manifest = null): void
    {
        $migrations = ($manifest ?? $addon->manifest)['migrations'] ?? [];

        if (!is_array($migrations)) {
            throw ValidationException::withMessages([
                'migrations' => 'Addon migrations contract must be an array.',
            ]);
        }

        foreach ($migrations as $migration) {
            if (!is_string($migration) || trim($migration) === '') {
                throw ValidationException::withMessages([
                    'migrations' => 'Each addon migration contract entry must be a non-empty string.',
                ]);
            }

            $this->recordStep($addon, 'migration_' . sha1($migration), "Migration contract registered: {$migration}");
        }
    }

    private function assertDependencies(array $dependencies): void
    {
        foreach ($dependencies as $dependency) {
            $identifier = is_string($dependency) ? $dependency : ($dependency['identifier'] ?? null);
            $constraint = is_string($dependency) ? null : ($dependency['constraint'] ?? null);

            if (!$identifier) {
                throw ValidationException::withMessages([
                    'dependencies' => 'Every addon dependency must contain an identifier.',
                ]);
            }

            $required = Addon::query()
                ->where('identifier', $identifier)
                ->where('status', 'active')
                ->first();

            if (!$required) {
                throw ValidationException::withMessages([
                    'dependencies' => "Required active addon dependency [{$identifier}] is not installed.",
                ]);
            }

            if ($constraint !== null && !$this->satisfiesConstraint($required->version, $constraint)) {
                throw ValidationException::withMessages([
                    'dependencies' => "Addon dependency [{$identifier}] version {$required->version} does not satisfy [{$constraint}].",
                ]);
            }
        }
    }

    private function assertCoreCompatibility(?string $constraint): void
    {
        if ($constraint !== null && trim($constraint) !== '' && !$this->satisfiesConstraint(
            (string) config('app.core_version', '2.0.0'),
            $constraint
        )) {
            throw ValidationException::withMessages([
                'compatibility' => 'Addon is not compatible with the installed SEMIZZY ONE Core version.',
            ]);
        }
    }

    private function satisfiesConstraint(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);

        if ($constraint === '' || $constraint === '*' || strtolower($constraint) === 'x') {
            return true;
        }

        if (preg_match('/^\^(\d+)\.(\d+)\.(\d+)$/', $constraint, $m)) {
            $major = (int) $m[1];
            $lower = "{$major}.{$m[2]}.{$m[3]}";
            $upper = ($major + 1) . '.0.0';

            return version_compare($version, $lower, '>=') && version_compare($version, $upper, '<');
        }

        if (preg_match('/^~(\d+)\.(\d+)\.(\d+)$/', $constraint, $m)) {
            $lower = "{$m[1]}.{$m[2]}.{$m[3]}";
            $upper = "{$m[1]}." . ((int) $m[2] + 1) . '.0';

            return version_compare($version, $lower, '>=') && version_compare($version, $upper, '<');
        }

        if (preg_match('/^(>=|<=|>|<|=)?\s*(\d+)\.(\d+)\.(\d+)$/', $constraint, $m)) {
            $operator = $m[1] ?: '=';
            return version_compare($version, "{$m[2]}.{$m[3]}.{$m[4]}", $operator);
        }

        throw ValidationException::withMessages([
            'compatibility' => "Unsupported version constraint [{$constraint}]. Use exact, comparison, ^x.y.z, or ~x.y.z syntax.",
        ]);
    }

    private function assertManifest(Addon $addon): void
    {
        $manifest = $addon->manifest ?? [];

        if (($manifest['identifier'] ?? null) !== $addon->identifier) {
            throw ValidationException::withMessages([
                'manifest' => 'Addon manifest identifier does not match the registered addon.',
            ]);
        }

        $this->validateManifest($manifest);
    }

    private function validateManifest(array $manifest): array
    {
        foreach (['identifier', 'name', 'version'] as $key) {
            if (!isset($manifest[$key]) || !is_string($manifest[$key]) || trim($manifest[$key]) === '') {
                throw ValidationException::withMessages([
                    $key => "Addon manifest field [{$key}] is required.",
                ]);
            }
        }

        if (!preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/i', $manifest['identifier'])) {
            throw ValidationException::withMessages([
                'identifier' => 'Addon identifier may contain only letters, numbers, dots, underscores, and hyphens.',
            ]);
        }

        if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $manifest['version'])) {
            throw ValidationException::withMessages([
                'version' => 'Addon version must use semantic-version format.',
            ]);
        }

        foreach (['dependencies', 'permissions', 'navigation', 'settings', 'migrations'] as $key) {
            if (isset($manifest[$key]) && !is_array($manifest[$key])) {
                throw ValidationException::withMessages([
                    $key => "Addon manifest field [{$key}] must be an array.",
                ]);
            }
        }

        $dependencyIds = [];

        foreach ($manifest['dependencies'] ?? [] as $dependency) {
            if (is_string($dependency)) {
                $dependencyIdentifier = trim($dependency);
                if ($dependencyIdentifier === '') {
                    throw ValidationException::withMessages(['dependencies' => 'Dependency identifiers must not be empty.']);
                }
                if (strcasecmp($dependencyIdentifier, $manifest['identifier']) === 0) {
                    throw ValidationException::withMessages(['dependencies' => 'An addon cannot depend on itself.']);
                }
                if (in_array($dependencyIdentifier, $dependencyIds, true)) {
                    throw ValidationException::withMessages(['dependencies' => "Duplicate addon dependency [{$dependencyIdentifier}]."]);
                }
                $dependencyIds[] = $dependencyIdentifier;
                continue;
            }

            if (!is_array($dependency) || !isset($dependency['identifier']) || !is_string($dependency['identifier'])) {
                throw ValidationException::withMessages([
                    'dependencies' => 'Dependency entries must be strings or objects with an identifier.',
                ]);
            }

            $dependencyIdentifier = trim($dependency['identifier']);
            if ($dependencyIdentifier === '') {
                throw ValidationException::withMessages(['dependencies' => 'Dependency identifiers must not be empty.']);
            }
            if (strcasecmp($dependencyIdentifier, $manifest['identifier']) === 0) {
                throw ValidationException::withMessages(['dependencies' => 'An addon cannot depend on itself.']);
            }
            if (in_array($dependencyIdentifier, $dependencyIds, true)) {
                throw ValidationException::withMessages(['dependencies' => "Duplicate addon dependency [{$dependencyIdentifier}]."]);
            }
            $dependencyIds[] = $dependencyIdentifier;

            if (isset($dependency['constraint']) && !is_string($dependency['constraint'])) {
                throw ValidationException::withMessages([
                    'dependencies' => 'Dependency constraints must be strings.',
                ]);
            }
        }

        foreach (['routes', 'api_routes', 'menus', 'widgets', 'services', 'provider_integrations', 'scheduled_tasks', 'events'] as $key) {
            if (isset($manifest[$key]) && !is_array($manifest[$key])) {
                throw ValidationException::withMessages([
                    $key => "Addon manifest field [{$key}] must be an array.",
                ]);
            }
        }

        if (isset($manifest['checksum']) && (!is_string($manifest['checksum']) || !preg_match('/^[A-Fa-f0-9]{64}$/', $manifest['checksum']))) {
            throw ValidationException::withMessages([
                'checksum' => 'Addon package checksum must be a SHA-256 hexadecimal string.',
            ]);
        }

        if (isset($manifest['compatibility']) && is_string($manifest['compatibility']) && trim($manifest['compatibility']) !== '') {
            $this->satisfiesConstraint((string) config('app.core_version', '2.0.0'), $manifest['compatibility']);
        }

        return $manifest;
    }
}
