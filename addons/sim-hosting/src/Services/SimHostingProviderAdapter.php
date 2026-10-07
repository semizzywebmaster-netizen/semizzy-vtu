<?php

namespace Semizzy\Addons\SimHosting\Services;

use App\Services\Providers\ProviderManager;
use App\Services\Providers\ProviderResult;
use RuntimeException;

final class SimHostingProviderAdapter
{
    public const SERVICE_KEY = 'sim-hosting';

    public function __construct(private ProviderManager $providers) {}

    public function airtime(array $payload, ?string $idempotencyKey = null): ProviderResult
    {
        return $this->execute('airtime_purchase', $payload, $idempotencyKey);
    }

    public function data(array $payload, ?string $idempotencyKey = null): ProviderResult
    {
        return $this->execute('data_purchase', $payload, $idempotencyKey);
    }

    public function dataCatalogue(array $payload = []): ProviderResult
    {
        return $this->execute('data_catalogue', $payload);
    }

    public function sms(array $payload, ?string $idempotencyKey = null): ProviderResult
    {
        return $this->execute('sms_send', $payload, $idempotencyKey);
    }

    public function balance(array $payload = []): ProviderResult
    {
        return $this->execute('provider_balance', $payload);
    }

    public function status(array $payload): ProviderResult
    {
        return $this->execute('transaction_status', $payload);
    }

    public function requery(array $payload): ProviderResult
    {
        return $this->execute('transaction_requery', $payload);
    }

    private function execute(string $operation, array $payload, ?string $idempotencyKey = null): ProviderResult
    {
        if (empty($payload) && in_array($operation, ['airtime_purchase','data_purchase','sms_send'], true)) {
            throw new RuntimeException('A provider payload is required for this operation.');
        }

        return $this->providers->execute(self::SERVICE_KEY, $operation, $payload, $idempotencyKey);
    }
}
