<?php

namespace Semizzy\Addons\CryptoPayments\Services;

use Semizzy\Addons\CryptoPayments\Contracts\CryptoPaymentGatewayAdapter;
use Semizzy\Addons\CryptoPayments\Models\CryptoPaymentProvider;
use RuntimeException;

class CryptoPaymentGatewayAdapterRegistry
{
    /** @var array<string, callable> */
    private array $factories = [];

    public function register(string $driver, callable $factory): void
    {
        $this->factories[$driver] = $factory;
    }

    public function has(string $driver): bool
    {
        return isset($this->factories[$driver]);
    }

    public function make(CryptoPaymentProvider $provider): CryptoPaymentGatewayAdapter
    {
        $factory = $this->factories[$provider->driver] ?? null;

        if ($factory === null) {
            throw new RuntimeException("No crypto adapter registered for driver [{$provider->driver}].");
        }

        $adapter = $factory($provider);

        if (! $adapter instanceof CryptoPaymentGatewayAdapter) {
            throw new RuntimeException("Crypto adapter for [{$provider->driver}] is invalid.");
        }

        return $adapter;
    }
}