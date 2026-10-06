<?php

namespace App\Services\Cac;

use App\Models\ApiProvider;
use App\Models\CacProviderRoute;
use App\Models\CacServiceProduct;
use Illuminate\Validation\ValidationException;

class CacProviderRouter
{
    public function routesFor(CacServiceProduct $product): array
    {
        return CacProviderRoute::query()
            ->with('provider')
            ->where('cac_service_product_id', $product->id)
            ->where('enabled', true)
            ->whereHas('provider', fn ($q) => $q->eligibleForNewTransactions())
            ->orderBy('priority')
            ->get()
            ->all();
    }

    public function select(CacServiceProduct $product): CacProviderRoute
    {
        $route = CacProviderRoute::query()
            ->with('provider')
            ->where('cac_service_product_id', $product->id)
            ->where('enabled', true)
            ->whereHas('provider', fn ($q) => $q->eligibleForNewTransactions())
            ->orderBy('priority')
            ->first();

        if (!$route) {
            throw ValidationException::withMessages([
                'provider' => 'No verified and enabled CAC provider is currently available for this service.',
            ]);
        }

        return $route;
    }
}
