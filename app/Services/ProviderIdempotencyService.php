<?php

namespace App\Services;

use App\Models\ApiProvider;
use App\Models\ProviderIdempotencyRecord;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProviderIdempotencyService
{
    public function reserve(ApiProvider $provider, string $idempotencyKey, array $requestPayload, int $lockSeconds = 120): array
    {
        $key = trim($idempotencyKey);
        if ($key === '' || strlen($key) > 191) {
            throw new \InvalidArgumentException('A valid idempotency key is required.');
        }

        $hash = hash('sha256', json_encode($this->canonicalize($requestPayload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
        $ttl = max(1, min($lockSeconds, 86400));

        return DB::transaction(function () use ($provider, $key, $hash, $ttl): array {
            $record = ProviderIdempotencyRecord::query()
                ->where('api_provider_id', $provider->id)
                ->where('idempotency_key', $key)
                ->lockForUpdate()
                ->first();

            if (!$record) {
                try {
                    $record = ProviderIdempotencyRecord::create([
                        'api_provider_id' => $provider->id,
                        'idempotency_key' => $key,
                        'request_hash' => $hash,
                        'state' => 'IN_PROGRESS',
                        'internal_reference' => (string) Str::uuid(),
                        'locked_until' => now()->addSeconds($ttl),
                    ]);

                    return ['created' => true, 'replay' => false, 'record' => $record];
                } catch (QueryException $e) {
                    if (!$this->isDuplicateKeyException($e)) {
                        throw $e;
                    }

                    $record = ProviderIdempotencyRecord::query()
                        ->where('api_provider_id', $provider->id)
                        ->where('idempotency_key', $key)
                        ->lockForUpdate()
                        ->first();

                    if (!$record) {
                        throw $e;
                    }
                }
            }

            if (!hash_equals($record->request_hash, $hash)) {
                throw new \RuntimeException('Idempotency key was already used with a different request payload.');
            }

            $state = strtoupper((string) $record->state);

            if ($state === 'COMPLETED') {
                return ['created' => false, 'replay' => true, 'record' => $record];
            }

            if ($state === 'UNKNOWN_PROCESSING_STATE') {
                return ['created' => false, 'replay' => true, 'unknown_processing_state' => true, 'record' => $record];
            }

            if ($state === 'IN_PROGRESS' && $record->locked_until?->isFuture()) {
                return ['created' => false, 'replay' => true, 'in_progress' => true, 'record' => $record];
            }

            $record->forceFill([
                'state' => 'IN_PROGRESS',
                'locked_until' => now()->addSeconds($ttl),
                'safe_error' => null,
                'completed_at' => null,
            ])->save();

            return ['created' => false, 'replay' => false, 'record' => $record];
        });
    }

    public function complete(ProviderIdempotencyRecord $record, string $transactionStatus, ?string $providerReference = null, ?array $safeResponse = null): ProviderIdempotencyRecord
    {
        $record->forceFill([
            'state' => 'COMPLETED',
            'transaction_status' => strtoupper($transactionStatus),
            'provider_reference' => $providerReference,
            'safe_response' => $safeResponse,
            'safe_error' => null,
            'locked_until' => null,
            'completed_at' => now(),
        ])->save();

        return $record->refresh();
    }

    public function fail(ProviderIdempotencyRecord $record, string $transactionStatus, string $safeError, bool $retryable = true, ?string $providerReference = null, ?array $safeResponse = null): ProviderIdempotencyRecord
    {
        $record->forceFill([
            'state' => $retryable ? 'FAILED_RETRYABLE' : 'UNKNOWN_PROCESSING_STATE',
            'transaction_status' => strtoupper($transactionStatus),
            'provider_reference' => $providerReference ?? $record->provider_reference,
            'safe_response' => $safeResponse ?? $record->safe_response,
            'safe_error' => $safeError,
            'locked_until' => $retryable ? null : now()->addDay(),
            'completed_at' => $retryable ? now() : null,
        ])->save();

        return $record->refresh();
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item) => $this->canonicalize($item), $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }

    private function isDuplicateKeyException(QueryException $e): bool
    {
        $sqlState = (string) $e->getCode();
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000' && in_array($driverCode, [1062, 1555, 2067], true);
    }
}
