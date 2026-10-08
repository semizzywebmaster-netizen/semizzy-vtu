<?php

namespace Semizzy\Addons\ForexDigitalAssets\Contracts;

use Semizzy\Addons\ForexDigitalAssets\Models\ForexDigitalAssetProvider;

interface MarketDataProviderDriver
{
    /**
     * Fetch and persist provider-sourced quotes.
     *
     * Implementations must use the provider's real response and must never
     * manufacture prices when an instrument or quote is unavailable.
     */
    public function refreshQuotes(ForexDigitalAssetProvider $provider): int;
}
