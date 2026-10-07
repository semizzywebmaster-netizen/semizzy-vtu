<?php
namespace Semizzy\Addons\TravelTickets\Services;

use App\Models\ApiProvider;
use App\Models\ProviderEndpoint;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class TravelProviderGateway
{
    public function candidates(string $capability): array
    {
        return ApiProvider::eligibleForNewTransactions()
            ->whereJsonContains('capabilities', $capability)
            ->orderBy('priority')
            ->get()
            ->all();
    }

    public function request(string $capability, string $operation, array $payload): array
    {
        foreach ($this->candidates($capability) as $provider) {
            $endpoint = $this->endpoint($provider->id, $operation);
            if (!$endpoint) {
                continue;
            }

            [$url, $query] = $this->endpointRequest($provider, $endpoint, $payload);
            $request = $this->requestClient($provider, $endpoint, $payload);

            try {
                $response = $this->send($request, $endpoint, $url, $query, $payload);

                if ($response->successful()) {
                    $provider->forceFill([
                        'last_successful_request_at' => now(),
                        'last_test_status' => 'success',
                    ])->saveQuietly();

                    return [
                        'provider' => $provider,
                        'endpoint' => $endpoint,
                        'response' => $this->responsePayload($response),
                    ];
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        throw new \RuntimeException('No eligible travel provider completed the requested operation.');
    }

    public function requestBooking(string $capability, string $operation, array $payload): array
    {
        $lastError = null;

        foreach ($this->candidates($capability) as $provider) {
            $endpoint = $this->endpoint($provider->id, $operation);
            if (!$endpoint) {
                continue;
            }

            [$url, $query] = $this->endpointRequest($provider, $endpoint, $payload);
            $request = $this->requestClient($provider, $endpoint, $payload);

            try {
                $response = $this->send($request, $endpoint, $url, $query, $payload);

                if ($response->successful()) {
                    $provider->forceFill([
                        'last_successful_request_at' => now(),
                        'last_test_status' => 'success',
                    ])->saveQuietly();

                    return [
                        'status' => 'accepted',
                        'provider' => $provider,
                        'endpoint' => $endpoint,
                        'response' => $this->responsePayload($response),
                    ];
                }

                if ($response->serverError() || in_array($response->status(), [408, 429], true)) {
                    return [
                        'status' => 'ambiguous',
                        'provider' => $provider,
                        'endpoint' => $endpoint,
                        'response' => $this->responsePayload($response),
                        'message' => 'Provider response is inconclusive; do not submit the booking to another provider.',
                    ];
                }

                $lastError = 'Provider rejected booking with HTTP '.$response->status();
            } catch (\Throwable $e) {
                return [
                    'status' => 'ambiguous',
                    'provider' => $provider,
                    'endpoint' => $endpoint,
                    'response' => null,
                    'message' => 'Provider request outcome is unknown; do not submit the booking to another provider.',
                ];
            }
        }

        throw new \RuntimeException($lastError ?: 'No eligible travel provider completed the booking operation.');
    }

    private function endpoint(int $providerId, string $operation): ?ProviderEndpoint
    {
        return ProviderEndpoint::where('api_provider_id', $providerId)
            ->where('operation', $operation)
            ->where('enabled', true)
            ->first();
    }

    private function requestClient(ApiProvider $provider, ProviderEndpoint $endpoint, array $payload): PendingRequest
    {
        $timeout = min(120, max(1, (int) ($provider->timeout_seconds ?: 30)));
        $request = Http::connectTimeout(min(15, $timeout))
            ->timeout($timeout)
            ->acceptJson();

        $headers = $this->resolveValues($endpoint->headers ?: [], $provider, $endpoint, $payload);
        if ($headers) {
            $request = $request->withHeaders($headers);
        }

        return $this->authenticate($request, $provider, $endpoint, $payload);
    }

    private function send(PendingRequest $request, ProviderEndpoint $endpoint, string $url, array $query, array $payload)
    {
        $method = strtoupper((string) ($endpoint->method ?: 'POST'));

        if ($method === 'GET') {
            return $request->get($url, array_merge($query, $payload));
        }

        if ($query) {
            $request = $request->withQueryParameters($query);
        }

        return $request->send($method, $url, ['json' => $payload]);
    }

    private function endpointRequest(ApiProvider $provider, ProviderEndpoint $endpoint, array $payload): array
    {
        $url = $endpoint->full_url ?: rtrim((string) $provider->base_url, '/').'/'.ltrim((string) $endpoint->path, '/');
        $query = $this->resolveValues($endpoint->query_params ?: [], $provider, $endpoint, $payload);

        return [$url, $query];
    }

    private function authenticate(PendingRequest $request, ApiProvider $provider, ProviderEndpoint $endpoint, array $payload): PendingRequest
    {
        $credentials = $provider->credentials ?: [];
        $mode = strtolower((string) ($endpoint->auth_mode ?: $provider->auth_type ?: 'none'));

        $apiKey = $credentials['api_key'] ?? $credentials['key'] ?? null;
        $token = $credentials['access_token'] ?? $credentials['token'] ?? $apiKey;
        $username = $credentials['username'] ?? null;
        $password = $credentials['password'] ?? null;

        if (in_array($mode, ['bearer', 'token'], true) && $token) {
            return $request->withToken((string) $token);
        }

        if (in_array($mode, ['api_key', 'apikey'], true) && $apiKey) {
            return $request->withHeaders(['X-API-Key' => (string) $apiKey]);
        }

        if (in_array($mode, ['basic', 'basic_auth'], true) && $username !== null) {
            return $request->withBasicAuth((string) $username, (string) ($password ?? ''));
        }

        if (in_array($mode, ['oauth2', 'oauth2_bearer'], true) && $token) {
            return $request->withToken((string) $token);
        }

        if (in_array($mode, ['header', 'custom_header'], true)) {
            $name = $credentials['auth_header'] ?? $credentials['header_name'] ?? null;
            $value = $credentials['auth_value'] ?? $credentials['header_value'] ?? $token;
            if ($name && $value) {
                return $request->withHeaders([(string) $name => (string) $value]);
            }
        }

        if (in_array($mode, ['query', 'query_api_key'], true) && $apiKey) {
            $name = $credentials['query_name'] ?? 'api_key';
            return $request->withQueryParameters([(string) $name => (string) $apiKey]);
        }

        return $request;
    }

    private function resolveValues(array $values, ApiProvider $provider, ProviderEndpoint $endpoint, array $payload): array
    {
        $credentials = $provider->credentials ?: [];
        $tokens = [
            '{api_key}' => (string) ($credentials['api_key'] ?? $credentials['key'] ?? ''),
            '{token}' => (string) ($credentials['access_token'] ?? $credentials['token'] ?? ''),
            '{username}' => (string) ($credentials['username'] ?? ''),
            '{password}' => (string) ($credentials['password'] ?? ''),
            '{public_key}' => (string) ($credentials['public_key'] ?? ''),
            '{private_key}' => (string) ($credentials['private_key'] ?? ''),
            '{transaction_pin}' => (string) ($credentials['transaction_pin'] ?? ''),
        ];

        return collect($values)->mapWithKeys(function ($value, $key) use ($tokens) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $value = strtr((string) $value, $tokens);
            return [(string) $key => $value];
        })->all();
    }

    private function responsePayload($response): mixed
    {
        try {
            return $response->json();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
