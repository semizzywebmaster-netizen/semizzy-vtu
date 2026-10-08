<?php

namespace Semizzy\Addons\ForexDigitalAssets\Services;

use Semizzy\Addons\ForexDigitalAssets\Models\ForexDigitalAssetProvider;

class ForexQuoteRefreshService
{
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

                $driver = trim((string) $provider->driver);
                if ($driver === '' || ! class_exists($driver)) {
                    $provider->update([
                        'last_failure_at' => now(),
                        'last_error' => 'No configured market-data driver is available for this provider.',
                    ]);
                    $result['failed']++;
                    return;
                }

                $adapter = app($driver);
                if (! $adapter instanceof \Semizzy\Addons\ForexDigitalAssets\Contracts\MarketDataProviderDriver) {
                    $provider->update([
                        'last_failure_at' => now(),
                        'last_error' => 'Configured provider driver does not expose refreshQuotes().',
                    ]);
                    $result['failed']++;
                    return;
                }

                try {
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
