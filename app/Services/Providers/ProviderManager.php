<?php

namespace App\Services\Providers;

use App\Models\ApiProvider;
use Illuminate\Support\Collection;
use RuntimeException;

class ProviderManager
{
    public function __construct(private ProviderCapabilityRegistry $registry, private RestJsonProviderAdapter $rest, private ProviderRequestLogger $logger) {}

    public function eligible(string $serviceKey,string $operation='transaction_initiation'): Collection
    {
        $query=ApiProvider::query()
            ->where('enabled',true)
            ->where('paused',false)
            ->whereHas('serviceMappings', function ($mapping) use ($serviceKey): void {
                $mapping->where('enabled', true)
                    ->where(function ($scope) use ($serviceKey): void {
                        $scope->whereHas('service', fn ($service) => $service->where('key', $serviceKey))
                            ->orWhere(function ($legacy) use ($serviceKey): void {
                                $legacy->whereNull('service_id')->where('service_key', $serviceKey);
                            });
                    });
            })
            ->with('serviceMappings.service')
            ->orderBy('priority');

        if ($operation === 'transaction_initiation') {
            $query->where('integration_status','live_verified')
                ->where('verification_status','live_verified');
        } else {
            $query->whereIn('integration_status',['live_verified','sandbox_verified'])
                ->whereIn('verification_status',['live_verified','sandbox_verified']);
        }

        return $query->get()->filter(function (ApiProvider $provider) use ($serviceKey, $operation): bool {
            $mapping = $provider->serviceMappings->first(function ($mapping) use ($serviceKey): bool {
                return ($mapping->service?->key === $serviceKey) || ($mapping->service_id === null && $mapping->service_key === $serviceKey);
            });
            if (! $mapping) return false;
            $capabilities = $mapping->capabilities;
            return ! is_array($capabilities) || $capabilities === [] || in_array($operation, $capabilities, true);
        })->values();
    }
    public function executeProvider(ApiProvider $provider,string $serviceKey,string $operation,array $payload=[],?string $idempotencyKey=null): ProviderResult
    {
        if(!$this->registry->supports($provider,$operation)) return new ProviderResult(false,'UNSUPPORTED',message:'Provider capability is not enabled.');
        try{$this->registry->validate($provider);}catch(\Throwable $e){return new ProviderResult(false,'UNSUPPORTED',message:'Provider configuration is invalid.');}
        $started=microtime(true);
        try {
            $result=$this->rest->execute($provider,$operation,$payload,$idempotencyKey);
        } catch (\\Throwable $e) {
            $result=new ProviderResult(
                accepted:false,
                status:'UNKNOWN',
                message:'Provider execution failed; provider state must be requeried before retry.',
                retryable:false,
                duplicateRisk:$operation==='transaction_initiation',
                providerId:$provider->id,
            );
        }
        $this->logger->record($provider,$operation,$serviceKey,$result,(int)round((microtime(true)-$started)*1000),$idempotencyKey);
        return new ProviderResult(
            accepted: $result->accepted,
            status: $result->status,
            providerReference: $result->providerReference,
            data: $result->data,
            message: $result->message,
            retryable: $result->retryable,
            duplicateRisk: $result->duplicateRisk,
            providerId: $provider->id,
        );
    }


    public function execute(string $serviceKey,string $operation,array $payload=[],?string $idempotencyKey=null): ProviderResult
    {
        $providers=$this->eligible($serviceKey,$operation);
        if($providers->isEmpty()) throw new RuntimeException('No verified provider is eligible for this service.');

        foreach($providers as $provider){
            if(!$this->registry->supports($provider,$operation)) continue;
            try {
                $this->registry->validate($provider);
            } catch (\Throwable $e) {
                continue;
            }
            $started=microtime(true);
            $result=$this->rest->execute($provider,$operation,$payload,$idempotencyKey);
            $this->logger->record($provider,$operation,$serviceKey,$result,(int)round((microtime(true)-$started)*1000),$idempotencyKey);
            $result = new ProviderResult(
                accepted: $result->accepted,
                status: $result->status,
                providerReference: $result->providerReference,
                data: $result->data,
                message: $result->message,
                retryable: $result->retryable,
                duplicateRisk: $result->duplicateRisk,
                providerId: $provider->id,
            );
            if($result->accepted) return $result;
            if($result->duplicateRisk || $result->status==='UNKNOWN') return $result;
        }
        return new ProviderResult(false,'FAILED',message:'All eligible providers failed.');
    }
}
