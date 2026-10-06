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
        return in_array($operation, ['health_check','balance_inquiry','catalogue_retrieval','transaction_initiation','transaction_status','refund','reversal','sms_send','whatsapp_send'], true);
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
                $credentials = $provider->credentials ?? [];
                $methodHeaders = $credentials[$isGet ? 'headers_get' : 'headers_post'] ?? null;

                // Support both method-specific headers and a shared custom header set.
                // This keeps providers such as Token-auth APIs configurable without
                // introducing provider-specific authentication code.
                if (!is_array($methodHeaders)) {
                    $methodHeaders = $credentials['headers'] ?? [];
                }

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

            $uncertainHttp = $status === 408 || $status === 429 || $status >= 500;

            return new ProviderResult(
                false,
                $uncertainHttp ? 'UNKNOWN' : 'FAILED',
                message: 'Provider request failed.',
                retryable: $uncertainHttp,
                // Timeouts/rate limits/server failures may occur after a provider
                // accepted the request, so transaction initiation must never fail
                // over automatically from these responses.
                duplicateRisk: $operation === 'transaction_initiation' && $uncertainHttp
            );
        } catch (\Throwable $e) {
            return new ProviderResult(
                false,
                'UNKNOWN',
                message: 'Provider request failed; provider state must be rechecked before retry.',
                retryable: false,
                duplicateRisk: $operation === 'transaction_initiation'
            );
        }
    }

    private function request(ApiProvider $provider): PendingRequest
    {
        $credentials = $provider->credentials ?? [];
        $request = Http::acceptJson();

        $request = match ($provider->auth_type) {
            'bearer' => $request->withToken((string)($credentials['token'] ?? $credentials['api_token'] ?? '')),
            'basic' => $request->withBasicAuth((string)($credentials['username'] ?? ''),(string)($credentials['password'] ?? '')),
            'api_key_header' => $request->withHeaders([(string)($credentials['header'] ?? 'X-API-Key') => (string)($credentials['key'] ?? $credentials['api_key'] ?? '')]),
            'api_key' => $request->withHeaders([(string)($credentials['api_key_name'] ?? 'X-API-Key') => (string)($credentials['api_key'] ?? '')]),
            'bearer_token' => $request->withToken((string)($credentials['api_token'] ?? $credentials['token'] ?? '')),
            'basic_auth' => $request->withBasicAuth((string)($credentials['username'] ?? ''), (string)($credentials['password'] ?? '')),
            'oauth2' => $request->withToken((string)($credentials['access_token'] ?? $credentials['api_token'] ?? '')),
            'custom' => $request->withHeaders($this->safeCredentialHeaders((array)($credentials['headers'] ?? []))),
            default => $request,
        };

        // Generic credential-to-header/query mapping. No provider names or credential
        // names are hard-coded into the integration registry: administrators decide
        // where each secret belongs.
        $mappedHeaders = (array)($credentials['credential_headers'] ?? []);
        if ($mappedHeaders) $request = $request->withHeaders($this->safeCredentialHeaders(array_map(
            fn($credentialKey) => $credentials[$credentialKey] ?? '',
            $mappedHeaders
        )));
        return $request;
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
        if (!is_array($body)) {
            return 'UNKNOWN';
        }

        $candidates = [
            $body['status'] ?? null,
            $body['data']['status'] ?? null,
            $body['data']['transaction_status'] ?? null,
            $body['transaction_status'] ?? null,
            $body['data']['state'] ?? null,
            $body['state'] ?? null,
        ];

        foreach ($candidates as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $normalized = strtolower(trim((string) $value));

            if (in_array($normalized, ['success','successful','completed','complete','accepted','approved','ok','done'], true)) {
                return 'ACCEPTED';
            }

            if (in_array($normalized, ['pending','processing','queued','in_progress','in-progress','initiated','submitted'], true)) {
                return 'PENDING';
            }

            if (in_array($normalized, ['failed','failure','error','rejected','declined','cancelled','canceled','denied'], true)) {
                return 'FAILED';
            }
        }

        foreach ([$body['success'] ?? null, $body['data']['success'] ?? null] as $success) {
            if ($success === true || $success === 1 || $success === '1' || $success === 'true') {
                return in_array($operation, ['health_check','balance_inquiry','catalogue_retrieval','transaction_initiation'], true)
                    ? 'ACCEPTED'
                    : 'UNKNOWN';
            }
            if ($success === false || $success === 0 || $success === '0' || $success === 'false') {
                return 'FAILED';
            }
        }

        // Read-only/catalogue endpoints commonly return data without a status.
        // A transaction initiation must never be inferred as accepted from HTTP 2xx alone.
        return in_array($operation, ['health_check','balance_inquiry','catalogue_retrieval'], true)
            ? 'ACCEPTED'
            : 'UNKNOWN';
    }
    private function providerReference(mixed $body): ?string
    {
        if (!is_array($body)) {
            return null;
        }

        $paths = [
            ['reference'],
            ['transaction_reference'],
            ['transactionReference'],
            ['request_id'],
            ['requestId'],
            ['order_id'],
            ['orderId'],
            ['data','reference'],
            ['data','transaction_reference'],
            ['data','transactionReference'],
            ['data','request_id'],
            ['data','requestId'],
            ['data','order_id'],
            ['data','orderId'],
        ];

        foreach ($paths as $path) {
            $value = $body;
            foreach ($path as $key) {
                if (!is_array($value) || !array_key_exists($key, $value)) {
                    $value = null;
                    break;
                }
                $value = $value[$key];
            }

            if (is_scalar($value) && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return null;
    }
}
