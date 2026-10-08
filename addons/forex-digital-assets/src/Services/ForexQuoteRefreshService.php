<?php

namespace Semizzy\Addons\ForexDigitalAssets\Services;

use Semizzy\Addons\ForexDigitalAssets\Models\ForexDigitalAssetProvider;

class ForexQuoteRefreshService
{
    public function __construct(private readonly MarketDataDriverResolver $drivers)
    {
    }

    public function refresh(): array
    {
        $result = ['providers' => 0, 'updated' => 0, 'failed' => 0];

        ForexDigitalAssetProvider::query()
            ->where('enabled', true)
            ->where('verified', true)
            ->where('is_market_data_provider', true)
            ->where('paused', false)
            ->where('maintenance', false)
            ->orderBy('priority')
            ->get()
            ->each(function (ForexDigitalAssetProvider $provider) use (&$result): void {
                $result['providers']++;

                try {
                    $adapter = $this->drivers->resolve($provider);
                    $updated = (int) $adapter->refreshQuotes($provider);
                    $provider->update([
                        'last_success_at' => now(),
                        'last_error' => null,
                        'last_health_check_at' => now(),
                    ]);
                    $result['updated'] += $updated;
                } catch (\Throwable $e) {
                    $provider->update([
                        'last_failure_at' => now(),
                        'last_error' => mb_substr($e->getMessage(), 0, 2000),
                        'last_health_check_at' => now(),
                    ]);
                    $result['failed']++;
                }
            });

        return $result;
    }
}
