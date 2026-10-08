<?php

namespace Semizzy\Addons\CryptoPayments\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Semizzy\Addons\CryptoPayments\Models\CryptoPaymentProvider;
use Semizzy\Addons\CryptoPayments\Models\CryptoPaymentTransaction;
use Semizzy\Addons\CryptoPayments\Services\CryptoPaymentGatewayAdapterRegistry;
use Semizzy\Addons\CryptoPayments\Services\CryptoPaymentSettlementService;
use RuntimeException;

class CryptoPaymentController
{
    public function create(
        Request $request,
        CryptoPaymentGatewayAdapterRegistry $registry
    ): JsonResponse {
        $data = $request->validate([
            'provider_code' => ['nullable', 'string', 'max:100'],
            'asset' => ['required', 'string', 'max:32'],
            'network' => ['nullable', 'string', 'max:64'],
            'fiat_amount' => ['required', 'numeric', 'gt:0'],
            'fiat_currency' => ['nullable', 'string', 'max:16'],
            'crypto_amount' => ['required', 'numeric', 'gt:0'],
            'idempotency_key' => ['required', 'string', 'max:191'],
            'metadata' => ['nullable', 'array'],
        ]);

        $existing = CryptoPaymentTransaction::where('idempotency_key', $data['idempotency_key'])
            ->where('user_id', $request->user()->id)
            ->first();
        if ($existing) {
            return response()->json(['data' => $existing], 200);
        }

        $provider = $data['provider_code']
            ? CryptoPaymentProvider::where('code', $data['provider_code'])->firstOrFail()
            : CryptoPaymentProvider::query()
                ->where('enabled', true)
                ->where('paused', false)
                ->where('maintenance', false)
                ->orderBy('priority')
                ->first();

        if (!$provider || !$provider->supports('crypto_payment', $data['asset'], $data['network'] ?? null)) {
            throw new RuntimeException('No eligible crypto payment provider is available.');
        }

        $reference = 'CRP-' . strtoupper(Str::random(20));
        $transaction = CryptoPaymentTransaction::create([
            'user_id' => $request->user()->id,
            'reference' => $reference,
            'idempotency_key' => $data['idempotency_key'],
            'crypto_payment_provider_id' => $provider->id,
            'asset' => strtoupper($data['asset']),
            'network' => $data['network'] ?? null,
            'fiat_amount' => $data['fiat_amount'],
            'fiat_currency' => strtoupper($data['fiat_currency'] ?? 'NGN'),
            'crypto_amount' => $data['crypto_amount'],
            'required_confirmations' => (int) config('crypto-payments.minimum_confirmations_default', 1),
            'expires_at' => now()->addMinutes((int) config('crypto-payments.payment_expiry_minutes', 30)),
            'metadata' => array_merge($data['metadata'] ?? [], [
                'expected_asset' => strtoupper($data['asset']),
                'expected_network' => strtoupper($data['network'] ?? ''),
            ]),
        ]);

        $adapter = $registry->make($provider);
        $result = $adapter->createPayment($transaction->toArray());

        $transaction->forceFill([
            'provider_payment_id' => isset($result['payment_id']) ? (string) $result['payment_id'] : null,
            'wallet_address' => $result['pay_address'] ?? $result['wallet_address'] ?? null,
            'crypto_amount' => $result['pay_amount'] ?? $transaction->crypto_amount,
            'exchange_rate' => $result['price_amount'] ?? $result['exchange_rate'] ?? null,
            'provider_payload' => $result,
            'status' => $result ? 'waiting' : 'pending',
        ])->save();

        return response()->json(['data' => $transaction->fresh()], 201);
    }

    public function status(Request $request, string $reference): JsonResponse
    {
        $transaction = CryptoPaymentTransaction::where('reference', $reference)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json(['data' => $transaction]);
    }
}
