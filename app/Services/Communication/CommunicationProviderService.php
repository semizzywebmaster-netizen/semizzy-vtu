<?php

namespace App\Services\Communication;

use App\Models\ApiProvider;
use App\Services\Providers\ProviderManager;
use RuntimeException;

class CommunicationProviderService
{
    public function __construct(private ProviderManager $providers) {}

    public function send(string $channel, string $recipient, string $message, array $extra = []): array
    {
        $operation = match ($channel) {
            'sms' => 'sms_send',
            'whatsapp' => 'whatsapp_send',
            default => throw new RuntimeException('Unsupported communication provider channel.'),
        };

        $serviceKey = $channel;
        $result = $this->providers->execute($serviceKey, $operation, array_merge([
            'to' => $recipient,
            'message' => $message,
        ], $extra));

        return [
            'accepted' => $result->accepted,
            'status' => $result->status,
            'provider_id' => $result->providerId,
            'provider_reference' => $result->providerReference,
            'retryable' => $result->retryable,
            'duplicate_risk' => $result->duplicateRisk,
            'message' => $result->message,
        ];
    }

    public function eligible(string $channel): array
    {
        return $this->providers->eligible($channel, $channel.'_send')
            ->map(fn (ApiProvider $provider) => [
                'id' => $provider->id,
                'name' => $provider->display_name,
                'priority' => $provider->priority,
                'enabled' => $provider->enabled,
            ])->values()->all();
    }
}
