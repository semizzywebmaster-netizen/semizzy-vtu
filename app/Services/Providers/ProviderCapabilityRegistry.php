<?php

namespace App\Services\Providers;

use App\Models\ApiProvider;
use InvalidArgumentException;

class ProviderCapabilityRegistry
{
    public const SUPPORTED_CAPABILITIES = [
        'balance_inquiry','catalogue_retrieval','transaction_initiation','transaction_status',
        'refund','reversal','webhook','health_check','kyc_verification',
        'number_reservation','number_release','inventory_sync','sms_send','sms_status',
    ];

    public function validate(ApiProvider $provider): void
    {
        $capabilities = $provider->capabilities ?? [];
        $unknown = array_values(array_diff($capabilities, self::SUPPORTED_CAPABILITIES));
        if ($unknown !== []) throw new InvalidArgumentException('Unsupported provider capabilities: '.implode(', ', $unknown));
        if (in_array('transaction_initiation',$capabilities,true) && !in_array('transaction_status',$capabilities,true))
            throw new InvalidArgumentException('Transaction initiation requires status inquiry or an explicitly reviewed reconciliation adapter.');
        if ($provider->enabled && $provider->integration_status === 'live_verified' && $provider->verification_status !== 'live_verified')
            throw new InvalidArgumentException('A provider cannot be live-enabled before verification.');
    }

    public function supports(ApiProvider $provider,string $operation): bool
    {
        return in_array($operation,$provider->capabilities ?? [],true);
    }
}
