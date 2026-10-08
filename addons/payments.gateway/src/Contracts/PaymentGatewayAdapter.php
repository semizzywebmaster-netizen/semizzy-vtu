<?php

namespace Semizzy\Addons\Payments\Contracts;

use Semizzy\Addons\Payments\Models\PaymentGatewayProvider;

interface PaymentGatewayAdapter
{
    public function initializeCollection(PaymentGatewayProvider $provider, array $payload): array;

    public function verifyCollection(PaymentGatewayProvider $provider, string $reference): array;

    public function nameEnquiry(PaymentGatewayProvider $provider, string $bankCode, string $accountNumber): array;

    public function singlePayout(PaymentGatewayProvider $provider, array $payload): array;

    public function bulkPayout(PaymentGatewayProvider $provider, array $payload): array;

    public function refund(PaymentGatewayProvider $provider, array $payload): array;

    public function healthCheck(PaymentGatewayProvider $provider): bool;
}