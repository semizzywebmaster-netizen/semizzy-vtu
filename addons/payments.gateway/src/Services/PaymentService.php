<?php

namespace Semizzy\Addons\Payments\Services;

use App\Models\WalletAccount;
use App\Services\Providers\ProviderManager;
use Illuminate\Support\Str;
use RuntimeException;
use Semizzy\Addons\Payments\Models\PaymentIntent;

final class PaymentService
{
    public function __construct(private ProviderManager $providers) {}

    public function createFundingIntent(int $userId, WalletAccount $wallet, string $amountMinor, string $currency = 'NGN'): PaymentIntent
    {
        if (!preg_match('/^[1-9]\d*$/', $amountMinor)) {
            throw new RuntimeException('Amount must be a positive integer minor-unit value.');
        }

        $reference = 'PAY-'.strtoupper(Str::random(20));
        $intent = PaymentIntent::create([
            'user_id' => $userId,
            'wallet_account_id' => $wallet->id,
            'reference' => $reference,
            'purpose' => 'wallet_funding',
            'currency' => strtoupper($currency),
            'amount_minor' => $amountMinor,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);

        try {
            $result = $this->providers->execute(
                'payments.gateway',
                'transaction_initiation',
                [
                    'reference' => $reference,
                    'amount_minor' => $amountMinor,
                    'currency' => strtoupper($currency),
                    'purpose' => 'wallet_funding',
                ],
                $reference
            );

            $data = is_array($result->data) ? $result->data : [];
            $checkoutUrl = data_get($data, 'checkout_url')
                ?? data_get($data, 'authorization_url')
                ?? data_get($data, 'data.checkout_url')
                ?? data_get($data, 'data.authorization_url');

            $intent->forceFill([
                'provider_id' => $result->providerId,
                'provider_reference' => $result->providerReference,
                'status' => strtolower($result->status),
                'checkout_url' => is_string($checkoutUrl) ? $checkoutUrl : null,
            ])->save();
        } catch (RuntimeException $e) {
            $intent->forceFill(['status' => 'provider_unavailable'])->save();
        }

        return $intent->fresh();
    }
}
