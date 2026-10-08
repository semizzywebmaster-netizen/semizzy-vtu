<?php

namespace Semizzy\Addons\CryptoPayments\Http\Controllers;

use App\Services\Fx\FxRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Semizzy\Addons\CryptoPayments\Models\CryptoPaymentProvider;
use Semizzy\Addons\CryptoPayments\Models\CryptoPaymentTransaction;
use Semizzy\Addons\CryptoPayments\Services\CryptoPaymentGatewayAdapterRegistry;
use Semizzy\Addons\CryptoPayments\Services\CryptoPaymentGatewayManager;
use Semizzy\Addons\CryptoPayments\Services\CryptoFundingSettingsService;
use RuntimeException;

class CryptoPaymentController
{
    public function create(
        Request $request,
        CryptoPaymentGatewayAdapterRegistry $registry,
        CryptoPaymentGatewayManager $manager,
        FxRateService $fx,
        CryptoFundingSettingsService $fundingSettings
    ): JsonResponse {
        $data = $request->validate([
            'provider_code' => ['nullable', 'string', 'max:100'],
            'asset' => ['required', 'string', 'max:32'],
            'network' => ['nullable', 'string', 'max:64'],
            'fiat_amount' => ['required', 'numeric', 'gt:0'],
            'fiat_currency' => ['nullable', 'string', 'max:16', 'in:NGN'],
            'crypto_amount' => ['nullable', 'numeric', 'gt:0'],
            'idempotency_key' => ['required', 'string', 'max:191'],
            'metadata' => ['nullable', 'array'],
        ]);

        $existing = CryptoPaymentTransaction::where('idempotency_key', $data['idempotency_key'])
            ->where('user_id', $request->user()->id)->first();
        if ($existing) return response()->json(['data' => $existing], 200);

        $fiatCurrency = strtoupper($data['fiat_currency'] ?? 'NGN');
        if ($fiatCurrency !== 'NGN') {
            throw new RuntimeException('Crypto wallet funding must be requested in NGN.');
        }
        $asset = strtoupper($data['asset']);
        $network = $data['network'] ? strtoupper($data['network']) : null;

        $fxSnapshot = $fiatCurrency === 'USD'
            ? ['rate' => 1.0, 'provider_id' => null, 'provider_code' => 'identity', 'fetched_at' => now()->toISOString()]
            : $fx->rate($fiatCurrency, 'USD');

        $funding = $fundingSettings->calculate((float) $data['fiat_amount']);
        $walletAmount = $funding['wallet_amount'];
        $fundingFee = $funding['fee_amount'];
        $customerFundingAmount = $funding['customer_crypto_funding_amount'];
        $usdAmount = $customerFundingAmount * (float) $fxSnapshot['rate'];
        $reference = 'CRP-' . strtoupper(Str::random(20));

        // Create one transaction before provider selection so failover never
        // collides with the unique idempotency key.
        $transaction = CryptoPaymentTransaction::create([
            'user_id' => $request->user()->id,
            'reference' => $reference,
            'idempotency_key' => $data['idempotency_key'],
            'crypto_payment_provider_id' => null,
            'asset' => $asset,
            'network' => $network,
            'fiat_amount' => $customerFundingAmount,
            'fiat_currency' => $fiatCurrency,
            'crypto_amount' => $data['crypto_amount'] ?? 0,
            'exchange_rate' => $fxSnapshot['rate'],
            'required_confirmations' => (int) config('crypto-payments.minimum_confirmations_default', 1),
            'expires_at' => now()->addMinutes((int) config('crypto-payments.payment_expiry_minutes', 30)),
            'metadata' => array_merge($data['metadata'] ?? [], [
                'platform_currency' => 'NGN',
                'funding' => [
                    'wallet_amount' => $walletAmount,
                    'fee_percent' => $funding['fee_percent'],
                    'fee_amount' => $fundingFee,
                    'customer_funding_amount' => $customerFundingAmount,
                ],
                'settlement_currency' => 'USD',
                'fx' => [
                    'from' => $fiatCurrency, 'to' => 'USD',
                    'rate' => $fxSnapshot['rate'],
                    'provider_id' => $fxSnapshot['provider_id'],
                    'provider_code' => $fxSnapshot['provider_code'],
                    'fetched_at' => $fxSnapshot['fetched_at'],
                    'usd_amount' => $usdAmount,
                ],
                'expected_asset' => $asset,
                'expected_network' => $network,
            ]),
        ]);

        $create = function (CryptoPaymentProvider $provider) use (
            $transaction, $registry, $data, $fiatCurrency, $asset, $network, $fxSnapshot, $usdAmount, $customerFundingAmount, $walletAmount, $fundingFee, $funding
        ) {
            if (!$provider->supports('crypto_payment', $asset, $network)) {
                throw new RuntimeException('Provider does not support the requested crypto asset/network.');
            }

            $transaction->forceFill(['crypto_payment_provider_id' => $provider->id])->save();

            try {
                $result = $registry->make($provider)->createPayment(array_merge($transaction->toArray(), [
                    'fiat_currency' => 'USD',
                    'fiat_amount' => $usdAmount,
                    'platform_fiat_amount' => $customerFundingAmount,
                    'wallet_credit_amount' => $walletAmount,
                    'funding_fee_amount' => $fundingFee,
                    'funding_fee_percent' => $funding['fee_percent'],
                    'platform_fiat_currency' => $fiatCurrency,
                    'fx_rate' => $fxSnapshot['rate'],
                ]));

                $transaction->forceFill([
                    'provider_payment_id' => isset($result['payment_id']) ? (string) $result['payment_id'] : null,
                    'wallet_address' => $result['pay_address'] ?? $result['wallet_address'] ?? null,
                    'crypto_amount' => $result['pay_amount'] ?? ($data['crypto_amount'] ?? null),
                    'provider_payload' => $result,
                    'status' => $result ? 'waiting' : 'pending',
                ])->save();

                return $transaction->fresh();
            } catch (\Throwable $e) {
                $transaction->forceFill([
                    'provider_payment_id' => null,
                    'wallet_address' => null,
                    'status' => 'pending',
                    'provider_payload' => [
                        'last_provider_error' => $e->getMessage(),
                    ],
                ])->save();
                throw $e;
            }
        };

        try {
            $result = $data['provider_code']
                ? $create(CryptoPaymentProvider::where('code', $data['provider_code'])->firstOrFail())
                : $manager->execute('crypto_payment', $create, $asset, $network);
        } catch (\Throwable $e) {
            $transaction->forceFill([
                'status' => 'failed',
                'provider_payload' => ['error' => $e->getMessage()],
            ])->save();
            throw $e;
        }

        return response()->json(['data' => $result], 201);
    }

    public function status(Request $request, string $reference): JsonResponse
    {
        $transaction = CryptoPaymentTransaction::where('reference', $reference)
            ->where('user_id', $request->user()->id)->firstOrFail();

        return response()->json(['data' => $transaction]);
    }
}
