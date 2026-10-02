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

            if ($existing?->trashed()) {
                throw ValidationException::withMessages([
                    'identifier' => 'An archived addon with this identifier already exists and must not be silently recreated.',
                ]);
            }

            if ($existing && in_array($existing->status, ['installing', 'validating'], true)) {
                throw ValidationException::withMessages([
                    'identifier' => 'The addon is already undergoing a lifecycle operation.',
                ]);
            }

            $addon = $existing ?? new Addon();
            $from = $addon->exists ? $addon->status : null;

            $addon->fill([
                'identifier' => $manifest['identifier'],
                'name' => $manifest['name'],
                'version' => $manifest['version'],
                'status' => $existing ? $existing->status : 'draft',
                'compatibility_constraint' => $manifest['compatibility'] ?? null,
                'dependencies' => $manifest['dependencies'] ?? [],
                'permissions' => $manifest['permissions'] ?? [],
                'navigation' => $manifest['navigation'] ?? [],
                'settings_schema' => $manifest['settings'] ?? [],
                'manifest' => $manifest,
                'package_checksum' => $manifest['checksum'] ?? null,
                'last_error' => null,
            ]);
            $addon->save();

            $addon->lifecycleEvents()->create([
                'addon_identifier' => $addon->identifier,
                'event' => 'registered',
                'from_status' => $from,
                'to_status' => $addon->status,
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
                $this->assertDependencies($addon);
                $this->transition($addon, 'installing', 'installing', 'Addon installation started.', $actorId);

                $this->recordStep($addon, 'register', 'Addon registration validated.');
                $this->recordStep($addon, 'migrate', 'Addon migration contract validated; no addon code is executed by Core.');
                $this->recordStep($addon, 'initialize', 'Addon initialization contract validated; no addon code is executed by Core.');
                $this->recordStep($addon, 'health', 'Addon health contract validated.');

                $from = $addon->status;
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
            $this->persistInstallFailure($addonId, $actorId, $e);
            throw $e;
        }
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

    public function archive(Addon $addon, ?int $actorId = null): Addon
    {
        return $this->transitionAndAudit($addon, 'archived', 'archived', 'Addon archived.', $actorId, [
            'draft',
            'installed',
            'inactive',
            'failed',
        ]);
    }

    private function persistInstallFailure(int $addonId, ?int $actorId, Throwable $exception): void
    {
        DB::transaction(function () use ($addonId, $actorId, $exception): void {
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
                'event' => 'install_failed',
                'from_status' => $from,
                'to_status' => 'failed',
                'message' => 'Addon installation failed; transaction changes were rolled back and diagnostics were persisted.',
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

    private function assertDependencies(Addon $addon): void
    {
        foreach ($addon->dependencies ?? [] as $dependency) {
            $identifier = is_string($dependency) ? $dependency : ($dependency['identifier'] ?? null);
            if (!$identifier) {
                throw ValidationException::withMessages([
                    'dependencies' => 'Every addon dependency must contain an identifier.',
                ]);
            }

            $required = Addon::query()
                ->where('identifier', $identifier)
                ->where('status', 'active')
                ->exists();

            if (!$required) {
                throw ValidationException::withMessages([
                    'dependencies' => "Required active addon dependency [{$identifier}] is not installed.",
                ]);
            }
        }
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
        $required = ['identifier', 'name', 'version'];

        foreach ($required as $key) {
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

        foreach (['dependencies', 'permissions', 'navigation', 'settings'] as $key) {
            if (isset($manifest[$key]) && !is_array($manifest[$key])) {
                throw ValidationException::withMessages([
                    $key => "Addon manifest field [{$key}] must be an array.",
                ]);
            }
        }

        return $manifest;
    }
}
