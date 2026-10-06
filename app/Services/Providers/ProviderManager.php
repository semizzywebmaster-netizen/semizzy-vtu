<?php

namespace App\Services\Providers;

use App\Models\ApiProvider;
use App\Services\ProviderIdempotencyService;
use Illuminate\Support\Collection;
use RuntimeException;

class ProviderManager
{
    public function __construct(
        private ProviderCapabilityRegistry $registry,
        private RestJsonProviderAdapter $rest,
        private ProviderRequestLogger $logger,
        private ProviderIdempotencyService $idempotency,
    ) {}

    public function eligible(string $serviceKey, string $operation = 'transaction_initiation'): Collection
    {
        $query = ApiProvider::query()
            ->where('enabled', true)
            ->where('paused', false)
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
            ->orderBy('priority')
            ->orderBy('id');

        if ($operation === 'transaction_initiation') {
            $query->where('integration_status', 'live_verified')
                ->where('verification_status', 'live_verified');
        } else {
            $query->whereIn('integration_status', ['live_verified', 'sandbox_verified'])
                ->whereIn('verification_status', ['live_verified', 'sandbox_verified']);
        }

        return $query->get()->filter(function (ApiProvider $provider) use ($serviceKey, $operation): bool {
            $mapping = $provider->serviceMappings->first(function ($mapping) use ($serviceKey): bool {
                return ($mapping->service?->key === $serviceKey)
                    || ($mapping->service_id === null && $mapping->service_key === $serviceKey);
            });

            if (! $mapping) {
                return false;
            }

            $capabilities = $mapping->capabilities;

            return is_array($capabilities) && in_array($operation, $capabilities, true);
        })->values();
    }

    public function executeProvider(
        ApiProvider $provider,
        string $serviceKey,
        string $operation,
        array $payload = [],
        ?string $idempotencyKey = null
    ): ProviderResult {
        if (! $this->mappingSupportsOperation($provider, $serviceKey, $operation)) {
            return new ProviderResult(false, 'UNSUPPORTED', message: 'Provider service mapping does not permit this operation.', providerId: $provider->id);
        }

        if (! $this->registry->supports($provider, $operation)) {
            return new ProviderResult(false, 'UNSUPPORTED', message: 'Provider capability is not enabled.', providerId: $provider->id);
        }

        try {
            $this->registry->validate($provider);
        } catch (\Throwable $e) {
            return new ProviderResult(false, 'UNSUPPORTED', message: 'Provider configuration is invalid.', providerId: $provider->id);
        }

        if ($operation === 'transaction_initiation' && filled($idempotencyKey)) {
            // The idempotency key is provider-scoped. Once a provider call becomes
            // ambiguous, execute() stops failover; definitive failures may safely
            // continue to the next provider.
            $reservation = $this->idempotency->reserve($provider, $idempotencyKey, $payload);

            if (($reservation['replay'] ?? false) === true) {
                $record = $reservation['record'];

                if (($reservation['in_progress'] ?? false) === true) {
                    return new ProviderResult(
                        false,
                        'PENDING',
                        providerReference: $record->provider_reference,
                        data: $record->safe_response,
                        message: 'An identical transaction is already in progress.',
                        retryable: false,
                        duplicateRisk: true,
                        providerId: $provider->id,
                    );
                }

                if (($reservation['unknown_processing_state'] ?? false) === true) {
                    return new ProviderResult(
                        false,
                        'UNKNOWN',
                        providerReference: $record->provider_reference,
                        data: $record->safe_response,
                        message: $record->safe_error ?: 'Provider state is uncertain; requery is required.',
                        retryable: false,
                        duplicateRisk: true,
                        providerId: $provider->id,
                    );
                }

                return new ProviderResult(
                    in_array(strtoupper((string) $record->transaction_status), ['SUCCESS', 'SUCCESSFUL', 'ACCEPTED', 'COMPLETED'], true),
                    strtoupper((string) ($record->transaction_status ?: 'PENDING')),
                    providerReference: $record->provider_reference,
                    data: $record->safe_response,
                    message: $record->safe_error,
                    retryable: false,
                    duplicateRisk: false,
                    providerId: $provider->id,
                );
            }
        } else {
            $reservation = null;
        }

        $started = microtime(true);

        try {
            $result = $this->rest->execute($provider, $operation, $payload, $idempotencyKey);
        } catch (\Throwable $e) {
            $result = new ProviderResult(
                accepted: false,
                status: 'UNKNOWN',
                message: 'Provider execution failed; provider state must be requeried before retry.',
                retryable: false,
                duplicateRisk: $operation === 'transaction_initiation',
                providerId: $provider->id,
            );
        }

        $result = $this->normalizeResult($result, $provider->id);

        if ($operation === 'transaction_initiation' && $reservation !== null) {
            if ($result->duplicateRisk || strtoupper($result->status) === 'UNKNOWN') {
                $this->idempotency->fail(
                    $reservation['record'],
                    'UNKNOWN_PROCESSING_STATE',
                    'Provider state is uncertain; requery is required before retry or reversal.',
                    false,
                    $result->providerReference,
                    $this->safeReplayResponse($result)
                );
            } else {
                $this->idempotency->complete(
                    $reservation['record'],
                    $result->status,
                    $result->providerReference,
                    $this->safeReplayResponse($result)
                );
            }
        }

        $this->logger->record(
            $provider,
            $operation,
            $serviceKey,
            $result,
            (int) round((microtime(true) - $started) * 1000),
            $idempotencyKey
        );

        return $result;
    }

    private function normalizeResult(ProviderResult $result, int $providerId): ProviderResult
    {
        return new ProviderResult(
            accepted: $result->accepted,
            status: $result->status,
            providerReference: $result->providerReference,
            data: $result->data,
            message: $result->message,
            retryable: $result->retryable,
            duplicateRisk: $result->duplicateRisk,
            providerId: $providerId,
        );
    }

    private function safeReplayResponse(ProviderResult $result): array
    {
        return array_filter([
            'accepted' => $result->accepted,
            'status' => $result->status,
            'provider_reference' => $result->providerReference,
            'message' => $result->message,
        ], static fn ($value) => $value !== null);
    }

    private function mappingSupportsOperation(ApiProvider $provider, string $serviceKey, string $operation): bool
    {
        $mapping = $provider->serviceMappings()
            ->where('enabled', true)
            ->where(function ($query) use ($serviceKey): void {
                $query->whereHas('service', fn ($service) => $service->where('key', $serviceKey))
                    ->orWhere(fn ($legacy) => $legacy->whereNull('service_id')->where('service_key', $serviceKey));
            })
            ->first();

        if (! $mapping) {
            return false;
        }

        $capabilities = $mapping->capabilities;

        return is_array($capabilities) && in_array($operation, $capabilities, true);
    }

    public function execute(string $serviceKey, string $operation, array $payload = [], ?string $idempotencyKey = null): ProviderResult
    {
        $providers = $this->eligible($serviceKey, $operation);

        if ($providers->isEmpty()) {
            throw new RuntimeException('No verified provider is eligible for this service.');
        }

        foreach ($providers as $provider) {
            if (! $this->registry->supports($provider, $operation)) {
                continue;
            }

            try {
                $this->registry->validate($provider);
            } catch (\Throwable $e) {
                continue;
            }

            $result = $this->executeProvider($provider, $serviceKey, $operation, $payload, $idempotencyKey);

            if ($result->accepted) {
                return $result;
            }

            $normalizedStatus = strtoupper((string) $result->status);

            if ($result->duplicateRisk || in_array($normalizedStatus, ['UNKNOWN', 'PENDING', 'PROCESSING', 'UNKNOWN_PROCESSING_STATE'], true)) {
                return $result;
            }
        }

        return new ProviderResult(false, 'FAILED', message: 'All eligible providers failed safely.');
    }
}
