<?php

namespace Semizzy\Addons\CryptoPayments\Providers;

use Illuminate\Support\ServiceProvider;
use Semizzy\Addons\CryptoPayments\Adapters\NowPaymentsAdapter;
use Semizzy\Addons\CryptoPayments\Models\CryptoPaymentProvider;
use Semizzy\Addons\CryptoPayments\Services\CryptoPaymentGatewayAdapterRegistry;

class CryptoPaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CryptoPaymentGatewayAdapterRegistry::class, function () {
            $registry = new CryptoPaymentGatewayAdapterRegistry();

            $registry->register('nowpayments', static fn (CryptoPaymentProvider $provider) => new NowPaymentsAdapter($provider));

            return $registry;
        });
    }
}