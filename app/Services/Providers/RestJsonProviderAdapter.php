<?php

namespace App\Services\Providers;

use App\Models\ApiProvider;
use App\Models\ProviderConnection;
use App\Models\ProviderEndpoint;
use App\Models\ProviderCredential;
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

        $connection = $provider->connections()
            ->where('enabled', true)
            ->orderByDesc('is_default')
            ->first();

        $configuredEndpoint = $connection
            ? $connection->provider()->exists()
                ? $provider->endpoints()->where('enabled', true)->where('operation', $operation)->latest('id')->first()
                : null
            : null;

        if ($connection && $configuredEndpoint) {
            return $this->executeConfiguredEndpoint($provider, $connection, $configuredEndpoint, $operation, $payload, $idempotencyKey);
        }

        // Backward-compatible legacy provider configuration path.
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
                retryable: false,
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

    private function executeConfiguredEndpoint(ApiProvider $provider, ProviderConnection $connection, ProviderEndpoint $endpoint, string $operation, array $payload, ?string $idempotencyKey): ProviderResult
    {
        $started = microtime(true);
        try {
            $url = $this->configuredEndpointUrl($connection, $endpoint);
            [$headers, $query, $body] = $this->configuredAuthentication($connection, $endpoint, $payload);
            $headers = array_merge($headers, (array) ($endpoint->headers ?? []));
            $query = array_merge($query, (array) ($endpoint->query_params ?? []));

            $request = Http::acceptJson()
                ->withHeaders($headers)
                ->connectTimeout(max(1, (int) $connection->connect_timeout_seconds))
                ->timeout(max(1, (int) $connection->request_timeout_seconds))
                ->withOptions(['allow_redirects' => false]);

            if (!$connection->verify_ssl) {
                $request = $request->withoutVerifying();
            }
            if ($idempotencyKey !== null && $idempotencyKey !== '') {
                $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
            }

            if ($query !== []) {
                $request = $request->withQueryParameters($query);
            }

            $response = match ($endpoint->content_type) {
                'query' => $request->request($endpoint->method, $url),
                'form-data' => $request->asMultipart()->request($endpoint->method, $url, $body),
                'x-www-form-urlencoded' => $request->asForm()->request($endpoint->method, $url, $body),
                'raw' => $request->withBody((string) ($body['raw'] ?? ''), 'text/plain')->request($endpoint->method, $url),
                default => $request->request($endpoint->method, $url, $body),
            };

            $bodyResponse = $response->json();
            if ($response->successful()) {
                $status = $this->normalizeStatus($bodyResponse, $operation);
                $accepted = $status === 'ACCEPTED';
                return new ProviderResult(
                    $accepted,
                    $status,
                    $this->providerReference($bodyResponse),
                    $bodyResponse,
                    $accepted ? 'Provider request accepted.' : 'Provider returned a non-success status.',
                    retryable: false,
                    duplicateRisk: $operation === 'transaction_initiation' && $status === 'UNKNOWN'
                );
            }

            $httpStatus = $response->status();
            $uncertain = $httpStatus === 408 || $httpStatus === 429 || $httpStatus >= 500;
            return new ProviderResult(
                false,
                $uncertain ? 'UNKNOWN' : 'FAILED',
                message: 'Provider request failed.',
                retryable: false,
                duplicateRisk: $operation === 'transaction_initiation' && $uncertain,
                providerId: $provider->id,
            );
        } catch (\Throwable $e) {
            return new ProviderResult(
                false,
                'UNKNOWN',
                message: 'Provider request failed; provider state must be rechecked before retry.',
                retryable: false,
                duplicateRisk: $operation === 'transaction_initiation',
                providerId: $provider->id,
            );
        }
    }

    private function configuredEndpointUrl(ProviderConnection $connection, ProviderEndpoint $endpoint): string
    {
        if (filled($endpoint->full_url)) {
            $this->guard->validate($endpoint->full_url);
            return $endpoint->full_url;
        }

        $this->guard->validate($connection->base_url);
        $prefix = trim((string) $connection->api_prefix, '/');
        $path = trim((string) $endpoint->path, '/');
        $url = rtrim($connection->base_url, '/') . ($prefix ? '/' . $prefix : '') . ($path ? '/' . $path : '');
        $this->guard->validate($url);
        return $url;
    }

    private function configuredAuthentication(ProviderConnection $connection, ProviderEndpoint $endpoint, array $payload): array
    {
        $body = $payload;
        $headers = (array) ($connection->headers ?? []);
        $query = (array) ($connection->query_params ?? []);

        if ($endpoint->auth_mode === 'none') {
            return [$headers, $query, $body];
        }

        $credentials = $connection->credentials()->get();
        if ($endpoint->auth_mode === 'connection' && $connection->auth_type === 'basic') {
            $username = $credentials->firstWhere('field_key', 'username')?->value;
            $password = $credentials->firstWhere('field_key', 'password')?->value;
            if ($username !== null && $password !== null) {
                $headers['Authorization'] = 'Basic ' . base64_encode($username . ':' . $password);
            }
        } elseif ($endpoint->auth_mode === 'connection') {
            foreach ($credentials as $credential) {
                if (!filled($credential->value)) continue;
                $value = (string) $credential->value;
                if ($credential->prefix) $value = $credential->prefix . ' ' . $value;
                if ($credential->placement === 'authorization') $headers['Authorization'] = $value;
                elseif ($credential->placement === 'header' && $credential->header_name) $headers[$credential->header_name] = $value;
                elseif ($credential->placement === 'query' && $credential->query_name) $query[$credential->query_name] = $value;
                elseif (in_array($credential->placement, ['body', 'form'], true) && $credential->body_path) data_set($body, $credential->body_path, $credential->value);
            }
        }

        return [$headers, $query, $body];
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
