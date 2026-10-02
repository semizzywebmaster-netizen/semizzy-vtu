<?php

namespace App\\Services\\Providers;

use App\\Models\\ApiProvider;
use Illuminate\\Http\\Client\\PendingRequest;
use Illuminate\\Support\\Facades\\Http;
use RuntimeException;

class RestJsonProviderAdapter implements ProviderAdapter
{
    public function supports(string $operation): bool
    {
        return in_array($operation, ['health_check','balance_inquiry','catalogue_retrieval','transaction_initiation','transaction_status','refund','reversal'], true);
    }

    public function execute(ApiProvider $provider, string $operation, array $payload = []): ProviderResult
    {
        if (! $this->supports($operation)) throw new RuntimeException("Unsupported REST operation: {$operation}");
        $url = $this->endpoint($provider, $operation);
        if ($url === null) return new ProviderResult(false, 'UNSUPPORTED', message: 'No endpoint is configured for this operation.');

        $request = $this->request($provider)->timeout(max(1, (int)$provider->timeout_seconds));
        try {
            $response = $operation === 'health_check' || $operation === 'balance_inquiry' || $operation === 'catalogue_retrieval'
                ? $request->get($url, $payload)
                : $request->post($url, $payload);

            if ($response->successful()) {
                $body = $response->json();
                return new ProviderResult(true, $this->normalizeStatus($body), $this->providerReference($body), $body, 'Provider request accepted.');
            }

            $status = $response->status();
            return new ProviderResult(false, $status >= 500 ? 'UNKNOWN' : 'FAILED', message: 'Provider HTTP '.$status, retryable: $status >= 500, duplicateRisk: $operation === 'transaction_initiation');
        } catch (\\Throwable $e) {
            return new ProviderResult(false, 'UNKNOWN', message: $e->getMessage(), retryable: true, duplicateRisk: $operation === 'transaction_initiation');
        }
    }

    private function request(ApiProvider $provider): PendingRequest
    {
        $credentials = $provider->credentials ?? [];
        $request = Http::acceptJson();
        return match ($provider->auth_type) {
            'bearer' => $request->withToken((string)($credentials['token'] ?? '')),
            'basic' => $request->withBasicAuth((string)($credentials['username'] ?? ''), (string)($credentials['password'] ?? '')),
            'api_key_header' => $request->withHeaders([(string)($credentials['header'] ?? 'X-API-Key') => (string)($credentials['key'] ?? '')]),
            default => $request->withHeaders((array)($credentials['headers'] ?? [])),
        };
    }

    private function endpoint(ApiProvider $provider, string $operation): ?string
    {
        $endpoints = $provider->capabilities['endpoints'] ?? [];
        $path = $endpoints[$operation] ?? null;
        if (!$path || !$provider->base_url) return null;
        return rtrim($provider->base_url, '/').'/'.ltrim($path, '/');
    }

    private function normalizeStatus(mixed $body): string
    {
        $value = is_array($body) ? strtolower((string)($body['status'] ?? $body['data']['status'] ?? 'accepted')) : 'accepted';
        return in_array($value, ['success','successful','completed','accepted'], true) ? 'ACCEPTED' : strtoupper($value);
    }

    private function providerReference(mixed $body): ?string
    {
        if (!is_array($body)) return null;
        return isset($body['reference']) ? (string)$body['reference'] : (isset($body['data']['reference']) ? (string)$body['data']['reference'] : null);
    }
}
