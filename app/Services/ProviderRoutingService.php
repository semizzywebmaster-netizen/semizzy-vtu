<?php

namespace App\Services;

use App\Models\ProviderRoutingRule;
use Illuminate\Support\Facades\DB;

class ProviderRoutingService
{
    public function candidates(int $productId, ?int $serviceId = null, ?int $categoryId = null): array
    {
        $scopeRank = ['PRODUCT' => 0, 'SERVICE' => 1, 'CATEGORY' => 2, 'GLOBAL' => 3];

        $rules = ProviderRoutingRule::query()
            ->where('enabled', true)
            ->with('provider')
            ->get()
            ->filter(function (ProviderRoutingRule $rule) use ($productId, $serviceId, $categoryId): bool {
                return match (strtoupper((string) $rule->scope_type)) {
                    'PRODUCT' => (int) $rule->scope_id === $productId,
                    'SERVICE' => $serviceId !== null && (int) $rule->scope_id === $serviceId,
                    'CATEGORY' => $categoryId !== null && (int) $rule->scope_id === $categoryId,
                    'GLOBAL' => $rule->scope_id === null,
                    default => false,
                };
            })
            ->sortBy(function (ProviderRoutingRule $rule) use ($scopeRank): array {
                return [
                    $scopeRank[strtoupper((string) $rule->scope_type)] ?? 99,
                    (int) $rule->priority,
                    (int) $rule->api_provider_id,
                    (int) $rule->id,
                ];
            });

        $rows = [];
        $seen = [];

        foreach ($rules as $rule) {
            $provider = $rule->provider;
            if (!$provider || !$provider->enabled || $provider->paused) {
                continue;
            }

            if (strtolower((string) $provider->integration_status) !== 'live_verified'
                || strtolower((string) $provider->verification_status) !== 'live_verified') {
                continue;
            }

            $mapping = DB::table('provider_product_mappings_v2')
                ->where('api_provider_id', $provider->id)
                ->where('catalogue_product_id', $productId)
                ->where('enabled', true)
                ->where('mapping_status', 'active')
                ->orderBy('priority')
                ->orderBy('id')
                ->first();

            if (!$mapping) {
                continue;
            }

            $dedupeKey = $provider->id . ':' . $mapping->id;
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $rows[] = [
                'provider_id' => $provider->id,
                'mapping_id' => $mapping->id,
                'provider_service_id' => $mapping->provider_service_id,
                'priority' => (int) $rule->priority,
                'scope_type' => strtoupper((string) $rule->scope_type),
                'max_attempts' => max(1, (int) $rule->max_attempts),
                'timeout_seconds' => max(1, (int) $rule->timeout_seconds),
                'conditions' => $rule->conditions ?? [],
            ];
        }

        return $rows;
    }

    public function isSafeToFailover(string $status): bool
    {
        return !in_array(strtoupper($status), ['UNKNOWN_PROCESSING_STATE', 'PROCESSING'], true);
    }
}
