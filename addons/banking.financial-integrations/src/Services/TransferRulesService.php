<?php

namespace Addons\BankingFinancialIntegrations\Services;

use RuntimeException;

class TransferRulesService
{
    public function bank(float $amount, array $settings = []): array
    {
        $limits = $settings;
        $min = ((int) ($limits['bank_transfer_min_amount_minor'] ?? 0)) / 100;
        $max = ((int) ($limits['bank_transfer_max_amount_minor'] ?? PHP_INT_MAX)) / 100;
        if ($amount < $min) {
            throw new RuntimeException('Transfer amount is below the configured minimum.');
        }
        if ($amount > $max) {
            throw new RuntimeException('Transfer amount exceeds the configured maximum.');
        }

        $components = (array) ($limits['bank_transfer_fee_components'] ?? []);
        $transferFee = ((int) ($components['transfer_fee_minor'] ?? $limits['bank_transfer_fee_minor'] ?? 0)) / 100;
        $vatFee = ((int) ($components['vat_minor'] ?? 0)) / 100;
        $otherFee = ((int) ($components['other_ng_fee_minor'] ?? 0)) / 100;
        $totalFee = $transferFee + $vatFee + $otherFee;

        return [
            'fee' => $totalFee,
            'transfer_fee' => $transferFee,
            'vat_fee' => $vatFee,
            'other_ng_fee' => $otherFee,
            'total_fee' => $totalFee,
            'total_debit' => $amount + $totalFee,
        ];
    }

    public function p2p(int $amountMinor, array $settings = []): array
    {
        $min = (int) ($settings['min_transfer_minor'] ?? 0);
        $max = (int) ($settings['max_transfer_minor'] ?? PHP_INT_MAX);
        if ($amountMinor < $min) {
            throw new RuntimeException('P2P transfer amount is below the configured minimum.');
        }
        if ($amountMinor > $max) {
            throw new RuntimeException('P2P transfer amount exceeds the configured maximum.');
        }

        $fee = (int) ($settings['fee_minor'] ?? 0);
        return [
            'fee_minor' => $fee,
            'total_debit_minor' => $amountMinor + $fee,
        ];
    }
}
