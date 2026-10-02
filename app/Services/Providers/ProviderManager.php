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
            ->whereHas('serviceMappings', fn($q)=>$q->where(function($m) use ($serviceKey) { $m->where('service_key',$serviceKey)->orWhereHas('service', fn($s)=>$s->where('key',$serviceKey)); })->where('enabled',true))
            ->orderBy('priority');

        if ($operation === 'transaction_initiation') {
            $query->where('integration_status','live_verified')
                ->where('verification_status','live_verified');
        } else {
            $query->whereIn('integration_status',['live_verified','sandbox_verified'])
                ->whereIn('verification_status',['live_verified','sandbox_verified']);
        }

        return $query->get();
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
            if($result->accepted) return $result;
            if($result->duplicateRisk || $result->status==='UNKNOWN') return $result;
        }
        return new ProviderResult(false,'FAILED',message:'All eligible providers failed.');
    }
}
