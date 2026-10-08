<?php

namespace Semizzy\Addons\CryptoPayments\Services;

use Illuminate\Support\Facades\DB;

class CryptoFundingSettingsService
{
    public const FEE_PERCENT_KEY = 'funding_fee_percent';
    public const FEE_AMOUNT_SCALE = 2;

    public function fundingFeePercent(): float
    {
        $value = DB::table('crypto_payment_settings')
            ->where('key', self::FEE_PERCENT_KEY)
            ->value('value');

        return max(0, min(100, (float) ($value ?? 0)));
    }

    public function setFundingFeePercent(float $percent): float
    {
        $percent = max(0, min(100, round($percent, 4)));

        DB::table('crypto_payment_settings')->updateOrInsert(
            ['key' => self::FEE_PERCENT_KEY],
            ['value' => (string) $percent, 'updated_at' => now(), 'created_at' => now()]
        );

        return $percent;
    }

    public function calculate(float $walletAmount): array
    {
        $percent = $this->fundingFeePercent();
        $fee = round($walletAmount * ($percent / 100), self::FEE_AMOUNT_SCALE);

        return [
            'wallet_amount' => round($walletAmount, 2),
            'fee_percent' => $percent,
            'fee_amount' => $fee,
            'customer_crypto_funding_amount' => round($walletAmount + $fee, 2),
        ];
    }
}
