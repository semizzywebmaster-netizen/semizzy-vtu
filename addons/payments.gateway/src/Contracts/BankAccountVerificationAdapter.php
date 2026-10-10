<?php

namespace Semizzy\Addons\Payments\Contracts;

use Semizzy\Addons\Payments\Models\PaymentGatewayProvider;

/** Explicit account-name validation contract, separate from payout initiation. */
interface BankAccountVerificationAdapter
{
    public function verifyAccount(PaymentGatewayProvider $provider, string $bankCode, string $accountNumber): array;
}
