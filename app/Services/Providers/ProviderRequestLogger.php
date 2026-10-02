<?php

namespace App\Services\Providers;

use App\Models\ApiProvider;
use App\Models\ProviderRequestLog;

class ProviderRequestLogger
{
    public function record(ApiProvider $provider,string $operation,?string $serviceKey,ProviderResult $result,?int $durationMs,?string $idempotencyKey=null): void
    {
        ProviderRequestLog::create([
            'api_provider_id'=>$provider->id,'operation'=>$operation,'service_key'=>$serviceKey,
            'status'=>$result->status,'provider_reference'=>$result->providerReference,
            'idempotency_key'=>$idempotencyKey,'duration_ms'=>$durationMs,
            'request_summary'=>'[REDACTED]','response_summary'=>$this->summary($result->data),
        ]);
    }

    private function summary(mixed $data): ?string
    {
        if ($data===null) return null;
        $encoded=json_encode($data,JSON_UNESCAPED_SLASHES);
        return $encoded===false?'[UNSERIALIZABLE]':mb_substr($encoded,0,4000);
    }
}
