<?php

namespace Semizzy\Addons\CryptoPayments\Contracts;

use Semizzy\Addons\CryptoPayments\Models\CryptoPaymentProvider;

interface CryptoPaymentGatewayAdapterFactory
{
    public function make(CryptoPaymentProvider $provider): CryptoPaymentGatewayAdapter;
}