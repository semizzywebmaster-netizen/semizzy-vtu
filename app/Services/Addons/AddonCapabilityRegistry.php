<?php

namespace App\Services\Addons;

use Illuminate\Support\Collection;

final class AddonCapabilityRegistry
{
    public function __construct(private AddonRegistry $addons) {}

    public function all(): Collection
    {
        // Only installed manifests are discoverable here. Runtime activation is
        // enforced by the caller when executing a capability.

        return collect($this->addons->all())
            ->flatMap(function (array $manifest): array {
                $capabilities = $manifest['capabilities'] ?? [];
                if (!is_array($capabilities)) return [];

                return collect($capabilities)->map(function ($capability) use ($manifest): array {
                    if (is_string($capability)) {
                        return [
                            'addon' => $manifest['identifier'],
                            'name' => $capability,
                            'execution_mode' => 'addon_defined',
                            'api_exposed' => true,
                        ];
                    }

                    if (is_array($capability) && !empty($capability['name'])) {
                        return array_merge([
                            'addon' => $manifest['identifier'],
                            'execution_mode' => 'addon_defined',
                            'api_exposed' => true,
                        ], $capability);
                    }

                    return [];
                })->filter()->values()->all();
            })
            ->values();
    }

    public function forAddon(string $identifier): Collection
    {
        return $this->all()->where('addon', strtolower(trim($identifier)))->values();
    }

    public function find(string $addon, string $name): ?array
    {
        $addon = strtolower(trim($addon));
        $name = strtolower(trim($name));

        return $this->all()->first(
            fn (array $capability): bool =>
                strtolower((string) ($capability['addon'] ?? '')) === $addon
                && strtolower((string) ($capability['name'] ?? '')) === $name
        );
    }

    public function apiCapabilities(): Collection
    {

        return $this->all()->filter(
            fn (array $capability): bool => ($capability['api_exposed'] ?? true) === true
        )->values();
    }
}
