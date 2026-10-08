<?php

namespace Semizzy\Addons\Payments\Services;

use Semizzy\Addons\Payments\Contracts\PaymentGatewayAdapter;
use RuntimeException;

final class PaymentGatewayAdapterRegistry
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

    public function make(string $driver): PaymentGatewayAdapter
    {
        if (!$this->has($driver)) {
            throw new RuntimeException("No payment gateway adapter registered for [{$driver}].");
        }

        $adapter = ($this->factories[$driver])();
        if (!$adapter instanceof PaymentGatewayAdapter) {
            throw new RuntimeException("Payment gateway adapter [{$driver}] does not implement the required contract.");
        }

        return $adapter;
    }
}
