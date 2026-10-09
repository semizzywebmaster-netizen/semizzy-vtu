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
        return in_array($operation, ['account_verification','health_check','health','status','balance_inquiry','catalogue_retrieval','catalogue','services','products','categories','transaction_initiation','transaction_status','refund','reversal','sms_send','sms_status','whatsapp_send','social_account_purchase','foreign_number_purchase','foreign_number_status','foreign_number_sms','number_reservation','number_release','kyc_verification','network_lookup'], true);
    }

    public function execute(ApiProvider $provider, string $operation, array $payload = [], ?string $idempotencyKey = null): ProviderResult
    {
        if ($provider->identifier === 'interswitch' && $operation === 'account_verification') {
            return $this->executeInterswitchAccountVerification($provider, $payload);
        }

        if (!$this->supports($operation)) {
            throw new RuntimeException("Unsupported REST operation: {$operation}");
        }

        $connection = $provider->connections()
            ->where('enabled', true)
            ->orderByDesc('is_default')
            ->first();

        $operationAliases = match ($operation) {
            'health_check' => ['health_check', 'health', 'status'],
            'health' => ['health', 'health_check', 'status'],
            'status' => ['status', 'health_check', 'health'],
            'catalogue_retrieval' => ['catalogue_retrieval', 'catalogue', 'services', 'products', 'categories'],
            'catalogue' => ['catalogue', 'catalogue_retrieval', 'services', 'products', 'categories'],
            'services' => ['services', 'catalogue_retrieval', 'catalogue'],
            'products' => ['products', 'catalogue_retrieval', 'catalogue'],
            'categories' => ['categories', 'catalogue_retrieval', 'catalogue'],
            'kyc_verification' => ['kyc_verification', 'identity_verification', 'kyc_check'],
            'network_lookup' => ['network_lookup', 'mnp_lookup', 'operator_lookup'],
            'foreign_number_status' => ['foreign_number_status', 'number_status', 'status'],
            'foreign_number_sms' => ['foreign_number_sms', 'sms_status', 'messages'],
            'network_lookup' => ['network_lookup', 'mnp_lookup', 'operator_lookup'],
            default => [$operation],
        };

        $configuredEndpoint = $connection
            ? $provider->endpoints()
                ->where('enabled', true)
                ->whereIn('operation', $operationAliases)
                ->latest('id')
                ->first()
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

            $isGet = in_array($operation, ['health_check','health','status','balance_inquiry','catalogue_retrieval','catalogue','services','products','categories','network_lookup'], true);
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

    /**
     * Interswitch's Nigerian account-name enquiry uses per-request signed auth,
     * not a static bearer token. Keep this read-only operation provider-specific.
     */
    private function executeInterswitchAccountVerification(ApiProvider $provider, array $payload): ProviderResult
    {
        $credentials = (array) ($provider->credentials ?? []);
        $clientId = (string) ($credentials['client_id'] ?? $credentials['clientId'] ?? '');
        $secretKey = (string) ($credentials['secret_key'] ?? $credentials['secretKey'] ?? '');
        $terminalId = (string) ($credentials['terminal_id'] ?? $credentials['terminalId'] ?? '');
        $bankCode = (string) ($payload['bank_code'] ?? $payload['bankCode'] ?? '');
        $accountNumber = (string) ($payload['account_number'] ?? $payload['accountId'] ?? '');

        if ($clientId === '' || $secretKey === '' || $terminalId === '' || $bankCode === '' || $accountNumber === '') {
            return new ProviderResult(false, 'FAILED', message: 'Interswitch account verification configuration or input is incomplete.', providerId: $provider->id);
        }

        $url = rtrim((string) $provider->base_url, '/') . '/nameenquiry/banks/accounts/names';

        try {
            $this->guard->validate($url);
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));
            $signatureSource = 'GET&' . urlencode($url) . '&' . $timestamp . '&' . $nonce . '&' . $clientId . '&' . $secretKey;
            $signature = base64_encode(sha1($signatureSource, true));

            $response = Http::acceptJson()
                ->withOptions(['allow_redirects' => false])
                ->timeout(max(1, (int) ($provider->timeout_seconds ?: 15)))
                ->withHeaders([
                    'Authorization' => 'InterswitchAuth ' . base64_encode($clientId),
                    'Content-Type' => 'application/json',
                    'Signature' => $signature,
                    'Timestamp' => $timestamp,
                    'Nonce' => $nonce,
                    'SignatureMethod' => 'SHA1',
                    'TerminalID' => $terminalId,
                    'bankCode' => $bankCode,
                    'accountId' => $accountNumber,
                ])->get($url);

            $body = $response->json();
            if ($response->successful() && is_array($body) && filled($body['accountName'] ?? null)) {
                return new ProviderResult(true, 'ACCEPTED', data: [
                    'account_name' => (string) $body['accountName'],
                    'bank_code' => $bankCode,
                    'account_number' => $accountNumber,
                ], message: 'Bank account name resolved.', providerId: $provider->id);
            }

            $status = $response->status();
            $uncertain = $status === 408 || $status === 429 || $status >= 500;
            return new ProviderResult(false, $uncertain ? 'UNKNOWN' : 'FAILED', message: 'Interswitch could not verify the supplied bank account.', retryable: false, providerId: $provider->id);
        } catch (\\Throwable) {
            return new ProviderResult(false, 'UNKNOWN', message: 'Interswitch verification request failed; check provider status before retrying.', retryable: false, providerId: $provider->id);
        }
    }

    private function executeConfiguredEndpoint(ApiProvider $provider, ProviderConnection $connection, ProviderEndpoint $endpoint, string $operation, array $payload, ?string $idempotencyKey): ProviderResult
    {
        $started = microtime(true);
        try {
            $url = $this->configuredEndpointUrl($connection, $endpoint);
            [$headers, $query, $body] = $this->configuredAuthentication($connection, $endpoint, $payload);
            $body = $this->mapRequestPayload($payload, (array) ($endpoint->request_mapping ?? []), $body);
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
                $request = $request->withOptions(['query' => $query]);
            }

            $response = match ($endpoint->content_type) {
                'query' => $request->withOptions(['query' => array_merge($query, $body)])->send($endpoint->method, $url),
                'form-data' => $request->asMultipart()->{$endpoint->method}($url, $body),
                'x-www-form-urlencoded' => $request->asForm()->{$endpoint->method}($url, $body),
                'raw' => $request->withBody((string) ($body['raw'] ?? ''), 'text/plain')->send($endpoint->method, $url),
                default => $request->{$endpoint->method}($url, $body),
            };

            $bodyResponse = $response->json();
            if ($response->successful()) {
                $mappedResponse = $this->mapResponsePayload($bodyResponse, (array) ($endpoint->response_mapping ?? []));
                $status = $this->normalizeStatus($mappedResponse, $operation);
                if ($status === 'UNKNOWN' && $mappedResponse !== $bodyResponse) {
                    $status = $this->normalizeStatus($bodyResponse, $operation);
                }
                $accepted = $status === 'ACCEPTED';
                return new ProviderResult(
                    $accepted,
                    $status,
                    $this->providerReference($mappedResponse) ?? $this->providerReference($bodyResponse),
                    $mappedResponse,
                    $accepted ? 'Provider request accepted.' : 'Provider returned a non-success status.',
                    retryable: false,
                    duplicateRisk: $operation === 'transaction_initiation' && $status === 'UNKNOWN',
                    providerId: $provider->id,
                );
            }

            $mappedError = $this->mapResponsePayload($bodyResponse, (array) ($endpoint->error_mapping ?? []));
            $httpStatus = $response->status();
            $uncertain = $httpStatus === 408 || $httpStatus === 429 || $httpStatus >= 500;
            return new ProviderResult(
                false,
                $uncertain ? 'UNKNOWN' : 'FAILED',
                message: $this->safeProviderMessage($mappedError),
                data: $mappedError !== [] ? $mappedError : null,
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

    /**
     * Apply an endpoint request mapping without breaking literal/template values.
     * Mapping format is target.path => source.path. If the source does not exist in
     * the internal payload, the configured value is retained as a literal.
     */
    private function mapRequestPayload(array $payload, array $mapping, array $fallbackBody): array
    {
        if ($mapping === []) {
            return $fallbackBody;
        }

        $mapped = [];
        foreach ($mapping as $target => $source) {
            if (!is_string($target) || $target === '') {
                continue;
            }

            if (is_string($source) && data_has($payload, $source)) {
                data_set($mapped, $target, data_get($payload, $source));
            } else {
                data_set($mapped, $target, $source);
            }
        }

        return $mapped !== [] ? $mapped : $fallbackBody;
    }

    private function mapResponsePayload(mixed $payload, array $mapping): mixed
    {
        if ($mapping === [] || !is_array($payload)) {
            return $payload;
        }

        $mapped = [];
        foreach ($mapping as $target => $source) {
            if (!is_string($target) || $target === '' || !is_string($source)) {
                continue;
            }
            $value = data_get($payload, $source);
            if ($value !== null) {
                data_set($mapped, $target, $value);
            }
        }

        return $mapped !== [] ? $mapped : $payload;
    }

    private function safeProviderMessage(mixed $mappedError): string
    {
        if (is_array($mappedError)) {
            foreach (['message', 'error', 'detail', 'description'] as $key) {
                $value = data_get($mappedError, $key);
                if (is_scalar($value) && trim((string) $value) !== '') {
                    return mb_substr(trim((string) $value), 0, 500);
                }
            }
        }

        return 'Provider request failed.';
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
                return in_array($operation, ['health_check','health','status','balance_inquiry','catalogue_retrieval','catalogue','services','products','categories','transaction_initiation'], true)
                    ? 'ACCEPTED'
                    : 'UNKNOWN';
            }
            if ($success === false || $success === 0 || $success === '0' || $success === 'false') {
                return 'FAILED';
            }
        }

        // Read-only/catalogue endpoints commonly return data without a status.
        // A transaction initiation must never be inferred as accepted from HTTP 2xx alone.
        return in_array($operation, ['health_check','health','status','balance_inquiry','catalogue_retrieval','catalogue','services','products','categories'], true)
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
