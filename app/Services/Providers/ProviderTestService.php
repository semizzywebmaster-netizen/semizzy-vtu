<?php

namespace App\\Services\\Providers;

use App\\Models\\ApiProvider;

class ProviderTestService
{
    public function __construct(private ProviderUrlGuard $guard, private RestJsonProviderAdapter $adapter) {}

    public function test(ApiProvider $provider): array
    {
        $this->guard->validate($provider->base_url);
        $started=microtime(true);
        $result=$this->adapter->execute($provider,'health_check');
        $ms=(int)round((microtime(true)-$started)*1000);
        $provider->forceFill([
            'last_tested_at'=>now(),
            'last_test_status'=>$result->status,
            'last_test_summary'=>$result->message ?: 'Health check completed.',
            'last_successful_request_at'=>$result->accepted ? now() : $provider->last_successful_request_at,
        ])->save();
        return ['result'=>$result,'duration_ms'=>$ms];
    }
}
