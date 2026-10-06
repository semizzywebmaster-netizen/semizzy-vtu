<?php

namespace Semizzy\Addons\Kyc\Services;

use App\Models\ApiProvider;
use Illuminate\Support\Collection;

class KycProviderResolver
{
    public function eligible(string $capability): Collection
    {
        return ApiProvider::query()
            ->eligibleForNewTransactions()
            ->where(function ($q) use ($capability) {
                $q->whereJsonContains('capabilities', $capability)
                  ->orWhereJsonContains('service_categories', 'kyc');
            })
            ->orderBy('priority')
            ->get();
    }
}
