<?php

namespace Semizzy\Addons\ForexDigitalAssets\Services;

use RuntimeException;
use Semizzy\Addons\ForexDigitalAssets\Contracts\MarketDataProviderDriver;
use Semizzy\Addons\ForexDigitalAssets\Models\ForexDigitalAssetProvider;

class MarketDataDriverResolver
{
    public function resolve(ForexDigitalAssetProvider $provider): MarketDataProviderDriver
    {
        $driver = trim((string) $provider->driver);

        if ($driver === '' || ! class_exists($driver)) {
            throw new RuntimeException('The configured market-data driver is unavailable.');
        }

        $instance = app($driver);

        if (! $instance instanceof MarketDataProviderDriver) {
            throw new RuntimeException('The configured market-data driver does not implement MarketDataProviderDriver.');
        }

        return $instance;
    }
}
