<?php

namespace Semizzy\Addons\Payments\Services;

use App\Models\WalletAccount;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;
use Semizzy\Addons\Payments\Models\PaymentIntent;

final class PaymentService
{
    public function __construct(private PaymentGatewayManager $gateways) {}

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
            $user = User::query()->findOrFail($userId);
            $result = $this->gateways->execute('collect_payment', function ($provider) use ($user, $wallet, $amountMinor, $currency, $reference) {
                $adapter = $this->gateways->adapter($provider);
                return [
                    'provider' => $provider,
                    'data' => $adapter->initializeCollection($provider, [
                        'reference' => $reference,
                        'amount_minor' => $amountMinor,
                        'amount' => ((int) $amountMinor) / 100,
                        'currency' => strtoupper($currency),
                        'customer_email' => $user->email,
                        'customer_name' => $user->name ?? null,
                        'description' => 'SEMIZZY ONE wallet funding',
                        'redirect_url' => url('/payments'),
                        'callback_url' => route('payments.webhook', ['provider' => $provider->code]),
                        'metadata' => ['wallet_account_id' => $wallet->id, 'purpose' => 'wallet_funding'],
                    ]),
                ];
            });

            $provider = $result['provider'];
            $data = is_array($result['data']) ? $result['data'] : [];
            $checkoutUrl = data_get($data, 'checkout_url')
                ?? data_get($data, 'authorization_url')
                ?? data_get($data, 'checkoutUrl')
                ?? data_get($data, 'cashierUrl');

            $providerReference = (string) (data_get($data, 'reference') ?? data_get($data, 'transactionReference') ?? $reference);

            $intent->forceFill([
                'provider_id' => $provider->id,
                'provider_reference' => $providerReference,
                'status' => 'pending',
                'checkout_url' => is_string($checkoutUrl) ? $checkoutUrl : null,
            ])->save();
        } catch (RuntimeException $e) {
            $intent->forceFill(['status' => 'provider_unavailable'])->save();
        }

        return $intent->fresh();
    }
}
