<?php

namespace App\Services\Vtu;

use App\Models\VtuTransaction;
use App\Services\Providers\ProviderManager;
use App\Services\Providers\ProviderResult;

class VtuProviderGateway
{
    public function __construct(private ProviderManager $providers) {}

    public function initiate(VtuTransaction $tx, array $payload): ProviderResult
    {
        $providers = $this->providers->eligible($tx->service->key, 'transaction_initiation');

        if ($providers->isEmpty()) {
            return new ProviderResult(false, 'FAILED', message: 'No verified provider is eligible for this service.');
        }

        $first = $providers->first(fn ($provider) => (int) $provider->id === (int) $tx->api_provider_id) ?? $providers->first();
        $result = $this->providers->executeProvider(
            $first,
            $tx->service->key,
            'transaction_initiation',
            $payload,
            $tx->idempotency_key
        );

        if ($this->mustStop($result)) {
            return $result;
        }

        foreach ($providers as $provider) {
            if ((int) $provider->id === (int) $first->id) {
                continue;
            }

            $result = $this->providers->executeProvider(
                $provider,
                $tx->service->key,
                'transaction_initiation',
                $payload,
                $tx->idempotency_key
            );

            if ($this->mustStop($result)) {
                return $result;
            }
        }

        return $result;
    }

    public function refund(VtuTransaction $tx, string $reason): ProviderResult
    {
        if (! $tx->provider_reference) {
            return new ProviderResult(false, 'UNKNOWN', message: 'Cannot refund without a provider reference.');
        }

        $provider = $tx->provider;

        if (! $provider) {
            return new ProviderResult(false, 'UNKNOWN', message: 'Original provider is unavailable; manual refund reconciliation is required.');
        }

        return $this->providers->executeProvider(
            $provider,
            $tx->service->key,
            'refund',
            [
                'reference' => $tx->provider_reference,
                'transaction_reference' => $tx->reference,
                'amount_minor' => $tx->total_minor,
                'currency' => $tx->currency,
                'reason' => $reason,
            ],
            $tx->idempotency_key . ':refund'
        );
    }

    public function requery(VtuTransaction $tx): ProviderResult
    {
        if (! $tx->provider_reference) {
            return new ProviderResult(false, 'UNKNOWN', message: 'No provider reference is available for requery.');
        }

        $provider = $tx->provider;

        if (! $provider) {
            return new ProviderResult(false, 'UNKNOWN', message: 'Original provider is unavailable; manual reconciliation is required.');
        }

        return $this->providers->executeProvider(
            $provider,
            $tx->service->key,
            'transaction_status',
            [
                'reference' => $tx->provider_reference,
                'transaction_reference' => $tx->reference,
            ],
            $tx->idempotency_key . ':requery'
        );
    }

    private function mustStop(ProviderResult $result): bool
    {
        return $result->accepted
            || $result->duplicateRisk
            || $result->status === 'UNKNOWN'
            || $result->status === 'PENDING';
    }
}
