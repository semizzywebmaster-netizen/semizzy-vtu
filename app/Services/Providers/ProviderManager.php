<?php

namespace App\\Services\\Providers;

use App\\Models\\ApiProvider;
use Illuminate\\Support\\Collection;
use RuntimeException;

class ProviderManager
{
    public function __construct(private ProviderCapabilityRegistry $registry, private RestJsonProviderAdapter $rest) {}

    public function eligible(string $serviceKey, string $operation = 'transaction_initiation'): Collection
    {
        return ApiProvider::query()->eligibleForNewTransactions()
            ->whereHas('serviceMappings', fn($q) => $q->where('service_key',$serviceKey)->where('enabled',true))
            ->orderBy('priority')->get();
    }

    public function execute(string $serviceKey, string $operation, array $payload = []): ProviderResult
    {
        $providers=$this->eligible($serviceKey,$operation);
        if($providers->isEmpty()) throw new RuntimeException('No verified provider is eligible for this service.');

        foreach($providers as $provider){
            $this->registry->validate($provider);
            $result=$this->rest->execute($provider,$operation,$payload);
            if($result->accepted) return $result;
            if($result->duplicateRisk || $result->status==='UNKNOWN') return $result;
        }
        return new ProviderResult(false,'FAILED',message:'All eligible providers failed.');
    }
}
