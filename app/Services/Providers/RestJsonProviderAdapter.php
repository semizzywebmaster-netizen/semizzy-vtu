<?php

namespace App\Services\Providers;

use App\Models\ApiProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RestJsonProviderAdapter implements ProviderAdapter
{
    public function __construct(private ProviderUrlGuard $guard) {}

    public function supports(string $operation): bool
    {
        return in_array($operation, ['health_check','balance_inquiry','catalogue_retrieval','transaction_initiation','transaction_status','refund','reversal'], true);
    }

    public function execute(ApiProvider $provider, string $operation, array $payload = [], ?string $idempotencyKey = null): ProviderResult
    {
        if (!$this->supports($operation)) {
            throw new RuntimeException("Unsupported REST operation: {$operation}");
        }

        $this->guard->validate($provider->base_url);

        $url = $this->endpoint($provider, $operation);
        if ($url === null) {
            return new ProviderResult(false, 'UNSUPPORTED', message: 'No endpoint is configured for this operation.');
        }

        try {
            // Provider hosts are SSRF-sensitive. Do not follow redirects automatically:
            // a validated public hostname could otherwise redirect the server into a
            // private/reserved address after the initial URL validation.
            $request = $this->request($provider)
                ->withOptions(['allow_redirects' => false])
                ->timeout(max(1, (int) $provider->timeout_seconds));

            if ($idempotencyKey !== null && $idempotencyKey !== '') {
                $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
            }

            $isGet = in_array($operation, ['health_check','balance_inquiry','catalogue_retrieval'], true);
            if ($provider->auth_type === 'custom') {
                $methodHeaders = $provider->credentials[$isGet ? 'headers_get' : 'headers_post'] ?? null;
                if (is_array($methodHeaders)) {
                    $request = $request->withHeaders($this->safeCredentialHeaders($methodHeaders));
                }
            }

            $response = $isGet
                ? $request->get($url, $payload)
                : $request->post($url, $payload);

            if ($response->successful()) {
                $body = $response->json();
                $normalized = $this->normalizeStatus($body, $operation);
                $accepted = $normalized === 'ACCEPTED';

                return new ProviderResult(
                    $accepted,
                    $normalized,
                    $this->providerReference($body),
                    $body,
                    $accepted ? 'Provider request accepted.' : 'Provider returned a non-success status.',
                    retryable: false,
                    duplicateRisk: $operation === 'transaction_initiation' && $normalized === 'UNKNOWN'
                );
            }

            $status = $response->status();

            return new ProviderResult(
                false,
                $status >= 500 ? 'UNKNOWN' : 'FAILED',
                message: 'Provider HTTP '.$status,
                retryable: $status >= 500,
                // Definitive 4xx rejections are safe to fail over. Only an
                // uncertain 5xx response carries duplicate risk.
                duplicateRisk: $operation === 'transaction_initiation' && $status >= 500
            );
        } catch (\Throwable $e) {
            return new ProviderResult(
                false,
                'UNKNOWN',
                message: 'Provider request failed. Check the provider configuration and server logs.',
                retryable: true,
                duplicateRisk: $operation === 'transaction_initiation'
            );
        }
    }

    private function request(ApiProvider $provider): PendingRequest
    {
        $credentials = $provider->credentials ?? [];

        return match ($provider->auth_type) {
            'bearer' => Http::acceptJson()->withToken((string) ($credentials['token'] ?? '')),
            'basic' => Http::acceptJson()->withBasicAuth(
                (string) ($credentials['username'] ?? ''),
                (string) ($credentials['password'] ?? '')
            ),
            'api_key_header' => Http::acceptJson()->withHeaders([
                (string) ($credentials['header'] ?? 'X-API-Key') => (string) ($credentials['key'] ?? ''),
            ]),
            default => Http::acceptJson()->withHeaders($this->safeCredentialHeaders((array) ($credentials['headers'] ?? []))),
        };
    }

    /**
     * Prevent accidental credential leakage through invalid/non-string header values.
     * Header names and values remain provider-controlled configuration and are never logged here.
     */
    private function safeCredentialHeaders(array $headers): array
    {
        $safe = [];
        foreach ($headers as $name => $value) {
            if (!is_string($name) || $name === '' || !is_scalar($value)) {
                continue;
            }
            $safe[$name] = (string) $value;
        }
        return $safe;
    }

    private function endpoint(ApiProvider $provider, string $operation): ?string
    {
        $path = ($provider->endpoints ?? [])[$operation] ?? null;

        if (!$path || !$provider->base_url) {
            return null;
        }

        if (!is_string($path) || preg_match('/^[a-z][a-z0-9+.-]*:/i', $path) || str_starts_with($path, '//')) {
            throw new RuntimeException('Provider endpoint must be a relative path.');
        }

        $parts = parse_url($path);
        if (
            $parts === false
            || isset($parts['scheme'], $parts['host'], $parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])
        ) {
            throw new RuntimeException('Provider endpoint must not contain a host, credentials, query string, or fragment.');
        }

        $url = rtrim($provider->base_url, '/').'/'.ltrim($path, '/');
        $this->guard->validate($url);

        return $url;
    }

    private function normalizeStatus(mixed $body, string $operation): string
    {
        $value = is_array($body)
            ? ($body['status'] ?? $body['data']['status'] ?? null)
            : null;

        if ($value === null || $value === '') {
            // Read-only/catalogue-style endpoints commonly return data without a status.
            // A transaction initiation must never be inferred as accepted from HTTP 2xx alone.
            return in_array($operation, ['health_check','balance_inquiry','catalogue_retrieval'], true)
                ? 'ACCEPTED'
                : 'UNKNOWN';
        }

        $normalized = strtolower(trim((string) $value));

        if (in_array($normalized, ['success','successful','completed','accepted','ok'], true)) {
            return 'ACCEPTED';
        }

        if (in_array($normalized, ['pending','processing','queued','in_progress'], true)) {
            return 'PENDING';
        }

        if (in_array($normalized, ['failed','failure','error','rejected','declined','cancelled','canceled'], true)) {
            return 'FAILED';
        }

        return strtoupper($normalized);
    }

    private function providerReference(mixed $body): ?string
    {
        if (!is_array($body)) {
            return null;
        }

        return isset($body['reference'])
            ? (string) $body['reference']
            : (isset($body['data']['reference']) ? (string) $body['data']['reference'] : null);
    }
}
