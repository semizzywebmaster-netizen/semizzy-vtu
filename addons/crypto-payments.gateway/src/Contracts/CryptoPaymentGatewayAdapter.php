<?php

namespace Semizzy\Addons\CryptoPayments\Contracts;

interface CryptoPaymentGatewayAdapter
{
    public function createPayment(array $payload): array;

    public function verifyPayment(array $payload): array;

    public function createInvoice(array $payload): array;

    public function createCheckout(array $payload): array;

    public function payout(array $payload): array;

    public function refund(array $payload): array;

    public function healthCheck(): array;
}