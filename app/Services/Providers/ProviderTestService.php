<?php

namespace App\Services\Providers;

use App\Models\ApiProvider;

class ProviderTestService
{
    public function __construct(private ProviderUrlGuard $guard, private RestJsonProviderAdapter $adapter) {}

    public function test(ApiProvider $provider): array
    {
        $this->guard->validate($provider->base_url);
        $started=microtime(true);
        // Prefer the self-service endpoint/connection engine. The legacy JSON
        // endpoint configuration is retained only as a backwards-compatible fallback.
        $configuredConnection = $provider->connections()->where('enabled', true)->orderByDesc('is_default')->first();
        if ($configuredConnection) {
            $configuredOperations = $provider->endpoints()->where('enabled', true)->pluck('operation')->filter()->values()->all();
            foreach (['health_check','balance_inquiry','catalogue_retrieval'] as $candidate) {
                if (in_array($candidate, $configuredOperations, true)) {
                    $result = $this->adapter->execute($provider, $candidate);
                    $ms = (int) round((microtime(true) - $started) * 1000);
                    $provider->forceFill([
                        'last_tested_at' => now(),
                        'last_test_status' => $result->status,
                        'last_test_summary' => $result->message ?: 'Health check completed.',
                        'last_successful_request_at' => $result->accepted ? now() : $provider->last_successful_request_at,
                    ])->save();
                    return ['result' => $result, 'duration_ms' => $ms, 'operation' => $candidate];
                }
            }
        }

        $rawEndpoints=$provider->getRawOriginal('endpoints');
        $endpoints=is_array($rawEndpoints) ? $rawEndpoints : (is_string($rawEndpoints) ? (json_decode($rawEndpoints,true) ?: []) : []);
        $capabilities=$provider->capabilities ?? [];
        $operation=null;

        foreach(['health_check','balance_inquiry','catalogue_retrieval'] as $candidate){
            if(isset($endpoints[$candidate]) && is_string($endpoints[$candidate]) && $endpoints[$candidate] !== ''
                && in_array($candidate,$capabilities,true)){
                $operation=$candidate;
                break;
            }
        }

        if($operation===null){
            $provider->forceFill([
                'last_tested_at'=>now(),
                'last_test_status'=>'FAILED',
                'last_test_summary'=>'No safe read-only verification endpoint is configured.',
            ])->save();

            return [
                'result'=>new ProviderResult(false,'FAILED',message:'No safe read-only verification endpoint is configured.'),
                'duration_ms'=>0,
                'operation'=>null,
            ];
        }

        $result=$this->adapter->execute($provider,$operation);
        $ms=(int)round((microtime(true)-$started)*1000);
        $provider->forceFill([
            'last_tested_at'=>now(),
            'last_test_status'=>$result->status,
            'last_test_summary'=>$result->message ?: 'Health check completed.',
            'last_successful_request_at'=>$result->accepted ? now() : $provider->last_successful_request_at,
        ])->save();
        return ['result'=>$result,'duration_ms'=>$ms,'operation'=>$operation];
    }
}
