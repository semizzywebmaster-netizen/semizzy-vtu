<?php

namespace App\Services\Providers;

use App\Models\ApiProvider;
use App\Models\ProviderRequestLog;

class ProviderRequestLogger
{
    private const SENSITIVE_KEYS = [
        'token', 'api_key', 'secret', 'password', 'authorization',
        'credential', 'private_key', 'signature', 'otp', 'pin', 'access_token', 'client_secret', 'api_secret', 'cookie', 'set_cookie', 'session', 'jwt', 'refresh_token', 'access_token', 'webhook_key',
    ];

    public function record(ApiProvider $provider, string $operation, ?string $serviceKey, ProviderResult $result, ?int $durationMs, ?string $idempotencyKey = null): void
    {
        ProviderRequestLog::create([
            'api_provider_id' => $provider->id,
            'operation' => $operation,
            'service_key' => $serviceKey,
            'status' => $result->status,
            'provider_reference' => $result->providerReference,
            'idempotency_key' => $idempotencyKey !== null && $idempotencyKey !== '' ? hash('sha256', $idempotencyKey) : null,
            'duration_ms' => $durationMs,
            'request_summary' => '[REDACTED]',
            'response_summary' => $this->summary($result->data),
        ]);
    }

    private function summary(mixed $data): ?string
    {
        if ($data === null) {
            return null;
        }

        $sanitized = $this->sanitize($data);
        $encoded = json_encode($sanitized, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $encoded === false ? '[UNSERIALIZABLE]' : mb_substr($encoded, 0, 4000);
    }

    private function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null) {
            $normalized = strtolower($key);
            foreach (self::SENSITIVE_KEYS as $needle) {
                if (str_contains($normalized, $needle)) {
                    return '[REDACTED]';
                }
            }
        }

        if (is_array($value)) {
            $safe = [];
            foreach ($value as $childKey => $childValue) {
                $safe[$childKey] = $this->sanitize($childValue, (string) $childKey);
            }

            return $safe;
        }

        if (is_object($value)) {
            return $this->sanitize((array) $value);
        }

        return is_scalar($value) || $value === null ? $value : '[UNSERIALIZABLE]';
    }
}
