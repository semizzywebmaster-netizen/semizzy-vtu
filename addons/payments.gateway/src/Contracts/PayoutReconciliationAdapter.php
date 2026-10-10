<?php

namespace Semizzy\Addons\Payments\Contracts;

use Semizzy\Addons\Payments\Models\PaymentGatewayProvider;

/** Provider-specific payout status lookup. A response is reconciliation evidence, not an automatic settlement command. */
interface PayoutReconciliationAdapter
{
    public function verifyPayout(PaymentGatewayProvider $provider, string $reference): array;
}
