<?php

namespace App\Services;

use App\Models\ProviderRoutingRule;
use Illuminate\Support\Facades\DB;

class ProviderRoutingService
{
    public function candidates(int $productId, ?int $serviceId = null, ?int $categoryId = null): array
    {
        $rules = ProviderRoutingRule::query()
            ->where('enabled', true)
            ->orderBy('priority')
            ->get();

        $rows = [];
        foreach ($rules as $rule) {
            $scopeMatches = match (strtoupper($rule->scope_type)) {
                'PRODUCT' => (int) $rule->scope_id === $productId,
                'SERVICE' => $serviceId !== null && (int) $rule->scope_id === $serviceId,
                'CATEGORY' => $categoryId !== null && (int) $rule->scope_id === $categoryId,
                'GLOBAL' => $rule->scope_id === null,
                default => false,
            };
            if (!$scopeMatches) continue;

            $provider = $rule->provider()->where('enabled', true)->where('paused', false)->first();
            if (!$provider) continue;

            $mapping = DB::table('provider_product_mappings_v2')
                ->where('api_provider_id', $provider->id)
                ->where('catalogue_product_id', $productId)
                ->where('enabled', true)
                ->where('mapping_status', 'active')
                ->orderBy('priority')
                ->first();

            if (!$mapping) continue;

            $rows[] = [
                'provider_id' => $provider->id,
                'mapping_id' => $mapping->id,
                'provider_service_id' => $mapping->provider_service_id,
                'priority' => $rule->priority,
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
