<?php

namespace App\Services\Fx;

use App\Models\FxRateProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FxRateService
{
    public function rate(string $from, string $to): array
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));
        if ($from === $to) return ['rate' => 1.0, 'provider_id' => null, 'provider_code' => 'identity', 'fetched_at' => now()];

        $providers = FxRateProvider::query()->where('enabled', true)->get()
            ->filter(fn (FxRateProvider $p) => $p->isAvailable())
            ->sortBy('priority')->values();

        $errors = [];
        foreach ($providers as $provider) {
            try {
                $result = $this->fetch($provider, $from, $to);
                $provider->forceFill([
                    'failure_count' => 0,
                    'last_success_at' => now(),
                    'last_checked_at' => now(),
                    'last_error' => null,
                ])->save();

                return [
                    'rate' => $result['rate'],
                    'provider_id' => $provider->id,
                    'provider_code' => $provider->code,
                    'fetched_at' => now(),
                    'raw' => $result['raw'] ?? null,
                ];
            } catch (\Throwable $e) {
                $errors[] = $provider->code;
                $failures = (int) $provider->failure_count + 1;
                $provider->forceFill([
                    'failure_count' => $failures,
                    'last_failure_at' => now(),
                    'last_checked_at' => now(),
                    'last_error' => substr($e->getMessage(), 0, 500),
                    'cooldown_until' => $failures >= 3 ? now()->addMinutes(5) : null,
                ])->save();
                Log::warning('FX provider failed', ['provider' => $provider->code, 'from' => $from, 'to' => $to]);
            }
        }

        throw new RuntimeException('No healthy FX provider could supply '.$from.'/'.$to.'. Tried: '.implode(', ', $errors));
    }

    private function fetch(FxRateProvider $provider, string $from, string $to): array
    {
        $settings = $provider->settings ?? [];
        $credentials = $provider->credentials;
        $url = (string) ($settings['url_template'] ?? $provider->base_url ?? '');
        if ($url === '') throw new RuntimeException('FX provider endpoint is not configured.');

        $url = str_replace(['{from}', '{to}'], [$from, $to], $url);
        $query = (array) ($settings['query'] ?? []);
        foreach ($query as $key => $value) {
            if (is_string($value)) $query[$key] = str_replace(['{from}', '{to}', '{api_key}'], [$from, $to, (string)($credentials['api_key'] ?? '')], $value);
        }

        $request = Http::timeout((int)($settings['timeout'] ?? 10))->retry(2, 200);
        $auth = (string)($settings['auth'] ?? 'none');
        if ($auth === 'bearer' && !empty($credentials['api_key'])) $request = $request->withToken((string)$credentials['api_key']);
        if ($auth === 'basic' && !empty($credentials['username'])) $request = $request->withBasicAuth((string)$credentials['username'], (string)($credentials['password'] ?? ''));

        $response = $request->get($url, $query);
        if (!$response->successful()) throw new RuntimeException('HTTP '.$response->status());

        $json = $response->json();
        $path = (string)($settings['rate_path'] ?? '');
        $value = $this->getPath($json, $path);

        if ($value === null && isset($json['rates'][$to])) $value = $json['rates'][$to];
        if ($value === null && isset($json['quotes'][$from.$to])) $value = $json['quotes'][$from.$to];
        if (!is_numeric($value) || (float)$value <= 0) throw new RuntimeException('Provider returned no usable rate.');

        return ['rate' => (float)$value, 'raw' => $json];
    }

    private function getPath(mixed $data, string $path): mixed
    {
        if ($path === '') return null;
        foreach (explode('.', $path) as $segment) {
            if (is_array($data) && array_key_exists($segment, $data)) $data = $data[$segment];
            else return null;
        }
        return $data;
    }
}