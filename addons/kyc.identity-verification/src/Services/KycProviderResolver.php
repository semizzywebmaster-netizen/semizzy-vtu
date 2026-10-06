<?php

namespace Semizzy\Addons\Kyc\Services;

use App\Models\ApiProvider;
use App\Services\Providers\ProviderManager;
use App\Services\Providers\ProviderResult;
use Illuminate\Support\Collection;

class KycProviderResolver
{
    public const SERVICE_KEY = 'kyc.identity-verification';
    public const OPERATION = 'identity_verification';

    public function __construct(private ProviderManager $providers) {}

    public function verify(array $payload, string $idempotencyKey): ProviderResult
    {
        return $this->providers->execute(self::SERVICE_KEY, self::OPERATION, $payload, $idempotencyKey);
    }

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
