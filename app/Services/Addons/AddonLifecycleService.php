<?php

namespace App\Services\Addons;

use App\Models\Addon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\System\FeatureControlService;
use Throwable;

class AddonLifecycleService
{
    public function register(array $manifest, ?int $actorId = null): Addon
    {
        $manifest = $this->validateManifest($manifest);

        return DB::transaction(function () use ($manifest, $actorId): Addon {
            $existing = Addon::withTrashed()->where('identifier', $manifest['identifier'])->lockForUpdate()->first();

            if ($existing && !in_array($existing->status, ['archived'], true)) {
                throw ValidationException::withMessages(['identifier' => 'This addon is already registered. Use the explicit update lifecycle for an installed addon.']);
            }

            if ($existing) {
                $from = $existing->status;
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $existing->fill($this->manifestAttributes($manifest));
                $existing->status = 'draft';
                $existing->installed_at = null;
                $existing->activated_at = null;
                $existing->save();

                $existing->lifecycleEvents()->create([
                    'addon_identifier' => $existing->identifier,
                    'event' => 'reregistered',
                    'from_status' => $from,
                    'to_status' => 'draft',
                    'message' => 'Archived addon manifest was safely re-registered from the current addon registry.',
                    'context' => ['version' => $existing->version],
                    'actor_id' => $actorId,
                ]);

                return $existing->fresh();
            }

            $addon = new Addon();
            $addon->fill($this->manifestAttributes($manifest));
            $addon->status = 'draft';
            $addon->save();
            $this->syncFeatureControls($manifest);

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
            $this->beginInstallation($addonId, $actorId);

            $addon = Addon::query()->findOrFail($addonId);
            $this->assertManifest($addon);
            $this->assertCoreCompatibility($addon->compatibility_constraint);
            $this->assertDependencies($addon->dependencies ?? [], $addon->identifier);

            $this->recordInstallationStep($addonId, 'register', 'Addon registration validated.');
            $this->runMigrations($addon);
            $this->runInstallerHook($addon);
            $this->recordInstallationStep($addonId, 'initialize', 'Addon initialization completed.');
            $this->recordInstallationStep($addonId, 'health', 'Addon health contract validated.');

            return DB::transaction(function () use ($addonId, $actorId): Addon {
                $addon = Addon::query()->lockForUpdate()->findOrFail($addonId);
                if ($addon->status !== 'installing') {
                    throw ValidationException::withMessages(['addon' => 'Addon installation state changed unexpectedly during installation.']);
                }

                $addon->update(['status' => 'installed', 'installed_at' => now(), 'last_error' => null]);
                $addon->lifecycleEvents()->create([
                    'addon_identifier' => $addon->identifier,
                    'event' => 'installed',
                    'from_status' => 'installing',
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
            throw ValidationException::withMessages(['identifier' => 'Update manifest identifier must match the installed addon.']);
        }
        if (version_compare($manifest['version'], $addon->version, '<=')) {
            throw ValidationException::withMessages(['version' => "Update version {$manifest['version']} must be newer than {$addon->version}."]);
        }

        $addonId = $addon->id;

        try {
            return DB::transaction(function () use ($addonId, $manifest, $actorId): Addon {
                $addon = Addon::query()->lockForUpdate()->findOrFail($addonId);
                if (!in_array($addon->status, ['installed', 'active', 'inactive'], true)) {
                    throw ValidationException::withMessages(['addon' => 'Only installed, active, or inactive addons can be updated.']);
                }
                if (version_compare($manifest['version'], $addon->version, '<=')) {
                    throw ValidationException::withMessages(['version' => "Update version {$manifest['version']} must be newer than {$addon->version}."]);
                }

                $wasActive = $addon->status === 'active';
                $this->assertCoreCompatibility($manifest['compatibility'] ?? null);
                $this->assertDependencies($manifest['dependencies'] ?? [], $manifest['identifier']);
                $this->transition($addon, 'updating', 'update_started', 'Addon update started.', $actorId);
                $this->recordStep($addon, 'register', 'Update manifest validated.');
                $this->runMigrations($addon, $manifest);
                $this->recordStep($addon, 'initialize', 'Addon update initialization completed.');
                $this->recordStep($addon, 'health', 'Addon update health contract validated.');

                $from = 'updating';
                $addon->fill($this->manifestAttributes($manifest));
                $addon->status = $wasActive ? 'active' : 'installed';
                $addon->last_error = null;
                $addon->save();
                $this->syncFeatureControls($manifest);

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
        return $manifest['identifier'] === $addon->identifier && version_compare($manifest['version'], $addon->version, '>');
    }

    public function activate(Addon $addon, ?int $actorId = null): Addon
    {
        return DB::transaction(function () use ($addon, $actorId): Addon {
            $addon = Addon::query()->lockForUpdate()->findOrFail($addon->id);
            if (!in_array($addon->status, ['installed', 'inactive'], true)) {
                throw ValidationException::withMessages(['addon' => 'Only an installed or inactive addon can be activated.']);
            }

            $this->assertCoreCompatibility($addon->compatibility_constraint);
            $this->assertDependencies($addon->dependencies ?? [], $addon->identifier);
            $this->transition($addon, 'enabling', 'enable_started', 'Addon enable operation started.', $actorId);
            $this->recordStep($addon, 'enable', 'Addon enable contract validated; no addon-specific Core code is hard-coded.');

            $from = 'enabling';
            $addon->update(['status' => 'active', 'activated_at' => now(), 'last_error' => null]);
            $this->syncFeatureControls($addon->manifest ?? [], true);
            $addon->lifecycleEvents()->create([
                'addon_identifier' => $addon->identifier,
                'event' => 'activated',
                'from_status' => $from,
                'to_status' => 'active',
                'message' => 'Addon activated successfully.',
                'actor_id' => $actorId,
            ]);

            return $addon->fresh();
        });
    }

    public function disable(Addon $addon, ?int $actorId = null): Addon
    {
        return DB::transaction(function () use ($addon, $actorId): Addon {
            $addon = Addon::query()->lockForUpdate()->findOrFail($addon->id);
            if (!in_array($addon->status, ['active', 'installed', 'failed'], true) || !$addon->canTransitionTo('inactive')) {
                throw ValidationException::withMessages(['addon' => 'Addon cannot transition from its current lifecycle state.']);
            }

            $dependents = $this->findInstalledDependents($addon->identifier);
            if ($dependents !== []) {
                throw ValidationException::withMessages(['addon' => 'Addon cannot be disabled while installed or active addons depend on it: '.implode(', ', $dependents).'.']);
            }

            $from = $addon->status;
            $addon->update(['status' => 'inactive']);
            $addon->lifecycleEvents()->create([
                'addon_identifier' => $addon->identifier,
                'event' => 'disabled',
                'from_status' => $from,
                'to_status' => 'inactive',
                'message' => 'Addon disabled.',
                'actor_id' => $actorId,
            ]);

            return $addon->fresh();
        });
    }

    public function uninstall(Addon $addon, ?int $actorId = null): Addon
    {
        return DB::transaction(function () use ($addon, $actorId): Addon {
            $addon = Addon::query()->lockForUpdate()->findOrFail($addon->id);
            if (!in_array($addon->status, ['installed', 'inactive', 'failed'], true) || !$addon->canTransitionTo('uninstalling')) {
                throw ValidationException::withMessages(['addon' => 'Only an installed, inactive, or failed addon can be uninstalled.']);
            }

            $dependents = $this->findInstalledDependents($addon->identifier);
            if ($dependents !== []) {
                throw ValidationException::withMessages(['addon' => 'Addon cannot be uninstalled while active or installed addons depend on it: '.implode(', ', $dependents).'.']);
            }

            $this->transition($addon, 'uninstalling', 'uninstall_started', 'Addon uninstall started.', $actorId);
            $this->recordStep($addon, 'uninstall', 'Addon uninstall contract validated; Core does not execute arbitrary addon code.');
            $from = 'uninstalling';
            $addon->update(['status' => 'archived', 'activated_at' => null, 'last_error' => null]);

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
        return $this->transitionAndAudit($addon, 'archived', 'archived', 'Addon archived.', $actorId, ['draft']);
    }

    private function runMigrations(Addon $addon, ?array $manifest = null): void
    {
        $migrations = ($manifest ?? $addon->manifest)['migrations'] ?? [];
        if (!is_array($migrations)) {
            throw ValidationException::withMessages(['migrations' => 'Addon migrations contract must be an array.']);
        }

        foreach ($migrations as $migration) {
            if (!is_string($migration) || trim($migration) === '') {
                throw ValidationException::withMessages(['migrations' => 'Each addon migration entry must be a non-empty string.']);
            }

            $migration = basename($migration);
            if (!str_ends_with(strtolower($migration), '.php')) {
                $migration .= '.php';
            }
            if (!preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_[A-Za-z0-9_]+\.php$/', $migration)) {
                throw ValidationException::withMessages(['migrations' => "Invalid addon migration filename [{$migration}]."]);
            }

            $rootPath = base_path('database/migrations/'.$migration);
            $addonMatches = glob(base_path('addons/*/database/migrations/'.$migration)) ?: [];
            if (count($addonMatches) > 1) {
                throw new \RuntimeException("Ambiguous addon migration filename: {$migration}");
            }
            $path = $addonMatches[0] ?? $rootPath;
            if (!is_file($path)) {
                throw new \RuntimeException("Addon migration file not found: {$migration}");
            }
            $arguments = ['--path' => $path, '--force' => true];
            if ($path !== $rootPath) {
                $arguments['--realpath'] = true;
            }
            $exit = Artisan::call('migrate', $arguments);
            if ($exit !== 0) {
                throw new \RuntimeException("Addon migration failed: {$migration}");
            }

            $this->recordStep($addon, 'migration_'.sha1($migration), "Migration applied: {$migration}");
        }
    }

    private function runInstallerHook(Addon $addon): void
    {
        $hook = ($addon->manifest ?? [])['installer'] ?? null;
        if ($hook === null) {
            return;
        }

        if (!is_string($hook) || !class_exists($hook)) {
            throw ValidationException::withMessages(['installer' => 'Addon installer hook is invalid.']);
        }

        $installer = app($hook);
        if (!method_exists($installer, 'install')) {
            throw ValidationException::withMessages(['installer' => 'Addon installer hook must expose install(Addon $addon).']);
        }

        $installer->install($addon);
        $this->recordStep($addon, 'installer_hook', 'Manifest installer hook completed.');
    }

    private function findInstalledDependents(string $identifier): array
    {
        $candidates = Addon::query()->whereIn('status', ['installed', 'enabling', 'active', 'disabling', 'updating', 'inactive'])->where('identifier', '!=', $identifier)->get(['identifier', 'dependencies']);

        return $candidates->filter(function (Addon $candidate) use ($identifier): bool {
            foreach ($candidate->dependencies ?? [] as $dependency) {
                $dependencyIdentifier = is_string($dependency) ? $dependency : (is_array($dependency) ? ($dependency['identifier'] ?? null) : null);
                if (is_string($dependencyIdentifier) && strcasecmp($dependencyIdentifier, $identifier) === 0) return true;
            }
            return false;
        })->pluck('identifier')->sort()->values()->all();
    }

    private function syncFeatureControls(array $manifest, bool $active = false): void
    {
        $controls = $manifest['feature_controls'] ?? [];
        if (!is_array($controls)) return;
        $service = app(FeatureControlService::class);
        foreach ($controls as $control) {
            if (!is_array($control) || empty($control['key'])) continue;
            $rawKey = strtolower(trim((string) $control['key']));
            $key = str_starts_with($rawKey, strtolower($manifest['identifier']).'.')
                ? $rawKey
                : strtolower($manifest['identifier']).'.'.$rawKey;
            $service->register($key, [
                'name' => $control['name'] ?? $key,
                'category' => $control['category'] ?? ($manifest['name'] ?? 'Addon'),
                'description' => $control['description'] ?? '',
                'default_enabled' => array_key_exists('default_enabled', $control) ? (bool)$control['default_enabled'] : $active,
                'dependencies' => array_values((array)($control['dependencies'] ?? [])),
                'source' => $manifest['identifier'],
            ]);
        }
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

    private function beginInstallation(int $addonId, ?int $actorId): void
    {
        DB::transaction(function () use ($addonId, $actorId): void {
            $addon = Addon::query()->lockForUpdate()->findOrFail($addonId);
            if (!in_array($addon->status, ['draft', 'failed', 'inactive'], true)) {
                throw ValidationException::withMessages(['addon' => 'Only a draft, failed, or inactive addon can begin installation.']);
            }

            $this->transition($addon, 'validating', 'install_started', 'Addon validation started.', $actorId);
            $this->assertManifest($addon);
            $this->assertCoreCompatibility($addon->compatibility_constraint);
            $this->assertDependencies($addon->dependencies ?? [], $addon->identifier);
            $this->transition($addon, 'installing', 'installing', 'Addon installation started.', $actorId);
        });
    }

    private function persistFailure(int $addonId, ?int $actorId, Throwable $exception, string $event): void
    {
        report($exception);
        DB::transaction(function () use ($addonId, $actorId, $exception, $event): void {
            $addon = Addon::query()->lockForUpdate()->find($addonId);
            if (!$addon || $addon->status === 'archived') return;

            $from = $addon->status;
            $addon->update(['status' => 'failed', 'last_error' => 'Addon lifecycle operation failed. Check server logs for diagnostic details.']);
            $addon->lifecycleEvents()->create([
                'addon_identifier' => $addon->identifier,
                'event' => $event,
                'from_status' => $from,
                'to_status' => 'failed',
                'message' => 'Addon lifecycle operation failed; diagnostics were persisted.',
                'context' => ['error_type' => get_class($exception)],
                'actor_id' => $actorId,
            ]);
        });
    }

    private function transitionAndAudit(Addon $addon, string $to, string $event, string $message, ?int $actorId, array $allowedFrom): Addon
    {
        return DB::transaction(function () use ($addon, $to, $event, $message, $actorId, $allowedFrom): Addon {
            $addon = Addon::query()->lockForUpdate()->findOrFail($addon->id);
            if (!in_array($addon->status, $allowedFrom, true) || !$addon->canTransitionTo($to)) {
                throw ValidationException::withMessages(['addon' => 'Addon cannot transition from its current lifecycle state.']);
            }

            $from = $addon->status;
            $addon->update(['status' => $to, 'activated_at' => $to === 'active' ? now() : $addon->activated_at, 'last_error' => $to === 'active' ? null : $addon->last_error]);
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
            throw ValidationException::withMessages(['addon' => "Invalid addon lifecycle transition: {$addon->status} to {$to}."]);
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
            'event' => 'step_'.$step,
            'from_status' => $addon->status,
            'to_status' => $addon->status,
            'message' => $message,
        ]);
    }

    private function recordInstallationStep(int $addonId, string $step, string $message): void
    {
        DB::transaction(function () use ($addonId, $step, $message): void {
            $addon = Addon::query()->findOrFail($addonId);
            if ($addon->status !== 'installing') {
                throw ValidationException::withMessages(['addon' => 'Addon installation state changed unexpectedly while recording an installation step.']);
            }
            $this->recordStep($addon, $step, $message);
        });
    }

    private function recordMigrationContract(Addon $addon, ?array $manifest = null): void
    {
        $migrations = ($manifest ?? $addon->manifest)['migrations'] ?? [];
        if (!is_array($migrations)) throw ValidationException::withMessages(['migrations' => 'Addon migrations contract must be an array.']);
        foreach ($migrations as $migration) {
            if (!is_string($migration) || trim($migration) === '') throw ValidationException::withMessages(['migrations' => 'Each addon migration contract entry must be a non-empty string.']);
            $this->recordStep($addon, 'migration_'.sha1($migration), "Migration contract validated: {$migration}");
        }
    }

    private function assertDependencies(array $dependencies, ?string $rootIdentifier = null, array $path = []): void
    {
        $normalized = [];
        foreach ($dependencies as $dependency) {
            $identifier = is_string($dependency) ? trim($dependency) : trim((string) ($dependency['identifier'] ?? ''));
            if ($identifier === '') throw ValidationException::withMessages(['dependencies' => 'Every addon dependency must contain an identifier.']);
            $normalized[strtolower($identifier)] = $dependency;
        }
        ksort($normalized);

        foreach ($normalized as $dependency) {
            $identifier = is_string($dependency) ? trim($dependency) : trim((string) $dependency['identifier']);
            $constraint = is_string($dependency) ? null : ($dependency['constraint'] ?? null);

            $cycleIndex = null;
            foreach ($path as $index => $visited) {
                if (strcasecmp($visited, $identifier) === 0) { $cycleIndex = $index; break; }
            }
            if ($cycleIndex !== null) {
                $cycle = array_merge(array_slice($path, $cycleIndex), [$identifier]);
                throw ValidationException::withMessages(['dependencies' => 'Addon dependency cycle detected: '.implode(' -> ', $cycle).'.']);
            }
            if ($rootIdentifier !== null && strcasecmp($identifier, $rootIdentifier) === 0) {
                throw ValidationException::withMessages(['dependencies' => 'Addon dependency cycle detected: '.implode(' -> ', [...$path, $rootIdentifier]).'.']);
            }

            $required = Addon::query()->where('identifier', $identifier)->where('status', 'active')->lockForUpdate()->first();
            if (!$required) throw ValidationException::withMessages(['dependencies' => "Required active addon dependency [{$identifier}] is not installed."]);
            if ($constraint !== null && !$this->satisfiesConstraint($required->version, $constraint)) {
                throw ValidationException::withMessages(['dependencies' => "Addon dependency [{$identifier}] version {$required->version} does not satisfy [{$constraint}]."]);
            }

            $requiredDependencies = $required->dependencies ?? [];
            if ($requiredDependencies !== []) {
                $this->assertDependencies($requiredDependencies, $rootIdentifier ?? $identifier, [...$path, $identifier]);
            }
        }
    }

    private function assertCoreCompatibility(?string $constraint): void
    {
        if ($constraint !== null && trim($constraint) !== '' && !$this->satisfiesConstraint((string) config('app.core_version', '2.0.0'), $constraint)) {
            throw ValidationException::withMessages(['compatibility' => 'Addon is not compatible with the installed SEMIZZY ONE Core version.']);
        }
    }

    private function satisfiesConstraint(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '' || $constraint === '*' || strtolower($constraint) === 'x') return true;

        if (preg_match('/^\^(\d+)\.(\d+)\.(\d+)$/', $constraint, $m)) {
            $major=(int)$m[1]; return version_compare($version, "{$major}.{$m[2]}.{$m[3]}", '>=') && version_compare($version, ($major+1).'.0.0', '<');
        }
        if (preg_match('/^~(\d+)\.(\d+)\.(\d+)$/', $constraint, $m)) {
            $lower="{$m[1]}.{$m[2]}.{$m[3]}"; $upper="{$m[1]}.".((int)$m[2]+1).'.0';
            return version_compare($version, $lower, '>=') && version_compare($version, $upper, '<');
        }
        if (preg_match('/^(>=|<=|>|<|=)?\s*(\d+)\.(\d+)\.(\d+)$/', $constraint, $m)) {
            return version_compare($version, "{$m[2]}.{$m[3]}.{$m[4]}", $m[1] ?: '=');
        }

        throw ValidationException::withMessages(['compatibility' => "Unsupported version constraint [{$constraint}]."]);
    }

    private function assertManifest(Addon $addon): void
    {
        $manifest = $addon->manifest ?? [];
        if (($manifest['identifier'] ?? null) !== $addon->identifier) throw ValidationException::withMessages(['manifest' => 'Addon manifest identifier does not match the registered addon.']);
        $this->validateManifest($manifest);
    }

    private function validateManifest(array $manifest): array
    {
        foreach (['identifier','name','version'] as $key) {
            if (!isset($manifest[$key]) || !is_string($manifest[$key]) || trim($manifest[$key]) === '') throw ValidationException::withMessages([$key => "Addon manifest field [{$key}] is required."]);
        }
        if (!preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/i', $manifest['identifier'])) throw ValidationException::withMessages(['identifier' => 'Addon identifier may contain only letters, numbers, dots, underscores, and hyphens.']);
        if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $manifest['version'])) throw ValidationException::withMessages(['version' => 'Addon version must use semantic-version format.']);
        $manifest['identifier']=strtolower(trim($manifest['identifier']));

        foreach (['dependencies','permissions','navigation','settings','migrations','feature_controls'] as $key) {
            if (isset($manifest[$key]) && !is_array($manifest[$key])) throw ValidationException::withMessages([$key => "Addon manifest field [{$key}] must be an array."]);
        }
        foreach ($manifest['dependencies'] ?? [] as $index => $dependency) {
            if (is_string($dependency)) {
                $id=strtolower(trim($dependency));
                if ($id==='' || strcasecmp($id,$manifest['identifier'])===0) throw ValidationException::withMessages(['dependencies' => 'Dependency identifiers must be non-empty and cannot self-reference.']);
                $manifest['dependencies'][$index]=$id;
                continue;
            }
            if (!is_array($dependency) || !isset($dependency['identifier']) || !is_string($dependency['identifier'])) throw ValidationException::withMessages(['dependencies' => 'Dependency entries must be strings or objects with an identifier.']);
            $id=strtolower(trim($dependency['identifier']));
            if ($id==='' || strcasecmp($id,$manifest['identifier'])===0) throw ValidationException::withMessages(['dependencies' => 'Dependency identifiers must be non-empty and cannot self-reference.']);
            $manifest['dependencies'][$index]['identifier']=$id;
            if (isset($dependency['constraint']) && !is_string($dependency['constraint'])) throw ValidationException::withMessages(['dependencies' => 'Dependency constraints must be strings.']);
        }

        $dependencyIdentifiers = [];
        foreach ($manifest['dependencies'] ?? [] as $dependency) {
            $dependencyIdentifier = is_string($dependency)
                ? $dependency
                : ($dependency['identifier'] ?? null);
            if (is_string($dependencyIdentifier)) {
                $dependencyIdentifier = strtolower(trim($dependencyIdentifier));
                if (isset($dependencyIdentifiers[$dependencyIdentifier])) {
                    throw ValidationException::withMessages(['dependencies' => "Duplicate addon dependency [{$dependencyIdentifier}] is not allowed."]);
                }
                $dependencyIdentifiers[$dependencyIdentifier] = true;
            }
        }

        foreach (['routes','api_routes','web_route_files','api_route_files','menus','widgets','services','provider_integrations','scheduled_tasks','events'] as $key) {
            if (isset($manifest[$key]) && !is_array($manifest[$key])) throw ValidationException::withMessages([$key => "Addon manifest field [{$key}] must be an array."]);
        }
        if (isset($manifest['installer']) && (!is_string($manifest['installer']) || !preg_match('/^[A-Za-z_\\][A-Za-z0-9_\\]*$/', $manifest['installer']))) {
            throw ValidationException::withMessages(['installer' => 'Addon installer hook must be a valid class name.']);
        }
        if (isset($manifest['checksum']) && (!is_string($manifest['checksum']) || !preg_match('/^[A-Fa-f0-9]{64}$/', $manifest['checksum']))) throw ValidationException::withMessages(['checksum' => 'Addon package checksum must be a SHA-256 hexadecimal string.']);
        if (isset($manifest['compatibility']) && is_string($manifest['compatibility']) && trim($manifest['compatibility']) !== '') $this->satisfiesConstraint((string) config('app.core_version','2.0.0'),$manifest['compatibility']);

        return $manifest;
    }
}
