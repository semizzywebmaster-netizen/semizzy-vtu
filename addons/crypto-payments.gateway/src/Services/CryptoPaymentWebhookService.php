<?php

namespace Semizzy\Addons\CryptoPayments\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Semizzy\Addons\CryptoPayments\Models\CryptoPaymentProvider;
use Semizzy\Addons\CryptoPayments\Models\CryptoPaymentTransaction;
use Semizzy\Addons\CryptoPayments\Models\CryptoPaymentWebhookEvent;
use RuntimeException;

class CryptoPaymentWebhookService
{
    public function process(CryptoPaymentProvider $provider, Request $request): CryptoPaymentWebhookEvent
    {
        $raw = $request->getContent();
        $signature = $request->header('x-nowpayments-sig')
            ?: $request->header('x-signature')
            ?: $request->header('x-webhook-signature');

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            throw new RuntimeException('Invalid crypto webhook payload.');
        }

        $eventKey = $this->eventKey($provider, $raw, $signature);
        $existing = CryptoPaymentWebhookEvent::where('event_key', $eventKey)->first();
        if ($existing) {
            return $existing;
        }

        $this->verifySignature($provider, $payload, $raw, $signature);

        $event = DB::transaction(function () use ($provider, $eventKey, $signature, $payload) {
            $event = CryptoPaymentWebhookEvent::create([
                'crypto_payment_provider_id' => $provider->id,
                'event_key' => $eventKey,
                'event_type' => $payload['payment_status'] ?? $payload['event_type'] ?? null,
                'provider_payment_id' => isset($payload['payment_id']) ? (string) $payload['payment_id'] : null,
                'signature' => $signature,
                'payload' => $payload,
            ]);

            if (!empty($payload['payment_id'])) {
                $tx = CryptoPaymentTransaction::where('provider_payment_id', (string) $payload['payment_id'])
                    ->where('crypto_payment_provider_id', $provider->id)
                    ->lockForUpdate()
                    ->first();

                if ($tx) {
                    $status = strtolower(trim((string) ($payload['payment_status'] ?? $tx->status)));
                    $terminalStatuses = ['finished', 'confirmed', 'failed', 'expired', 'refunded', 'cancelled'];
                    $knownStatuses = [
                        'waiting', 'pending', 'confirming', 'confirmed', 'finished',
                        'failed', 'expired', 'refunded', 'cancelled', 'underpaid',
                        'partially_paid', 'sending', 'paid',
                    ];

                    // Webhooks can arrive late or out of order. Once a transaction
                    // reaches a terminal state, do not let an older callback
                    // downgrade it (for example, finished -> failed/waiting).
                    // Unknown provider statuses are recorded as events but never
                    // written into the transaction state machine.
                    if (
                        !in_array(strtolower((string) $tx->status), $terminalStatuses, true)
                        && in_array($status, $knownStatuses, true)
                    ) {
                        $tx->forceFill([
                            'status' => $status,
                            'crypto_received' => $payload['actually_paid'] ?? $tx->crypto_received,
                            'tx_hash' => $payload['outcome']['txHash'] ?? $payload['tx_hash'] ?? $tx->tx_hash,
                            'confirmations' => (int) ($payload['confirmations'] ?? $tx->confirmations),
                            'provider_payload' => $payload,
                            'paid_at' => in_array($status, ['paid', 'finished', 'confirmed'], true) ? ($tx->paid_at ?? now()) : $tx->paid_at,
                            'confirmed_at' => in_array($status, ['finished', 'confirmed'], true) ? ($tx->confirmed_at ?? now()) : $tx->confirmed_at,
                        ])->save();
                    }
                }
            }

            $event->forceFill(['processed_at' => now()])->save();
            return $event;
        });

        if ($event->provider_payment_id) {
            $tx = CryptoPaymentTransaction::query()
                ->where('crypto_payment_provider_id', $provider->id)
                ->where('provider_payment_id', $event->provider_payment_id)
                ->first();

            if ($tx) {
                $settlement = app(CryptoPaymentSettlementService::class);
                $settlement->evaluate($tx);
                $tx = $tx->fresh();

                if ($settlement->canSettle($tx)) {
                    $settlement->markSettled(
                        $tx,
                        'CRYPTO-SETTLE-'.$tx->uuid
                    );
                }
            }
        }

        return $event;
    }

    private function verifySignature(CryptoPaymentProvider $provider, array $payload, string $raw, ?string $signature): void
    {
        $credentials = $provider->credentials ?? [];
        $secret = $credentials['ipn_secret'] ?? null;

        if (!$secret) {
            throw new RuntimeException('Crypto webhook secret is not configured.');
        }

        if (!$signature) {
            throw new RuntimeException('Crypto webhook signature is missing.');
        }

        $canonical = $this->canonicalJson($payload);
        $expected = hash_hmac('sha512', $canonical, $secret);

        if (!hash_equals(strtolower($expected), strtolower(trim($signature)))) {
            throw new RuntimeException('Invalid crypto webhook signature.');
        }
    }

    private function canonicalJson(array $payload): string
    {
        $sort = function ($value) use (&$sort) {
            if (!is_array($value)) return $value;
            if (array_is_list($value)) return array_map($sort, $value);
            ksort($value);
            foreach ($value as $key => $item) $value[$key] = $sort($item);
            return $value;
        };

        return json_encode($sort($payload), JSON_UNESCAPED_SLASHES);
    }

    private function eventKey(CryptoPaymentProvider $provider, string $raw, ?string $signature): string
    {
        return hash('sha256', $provider->id . '|' . ($signature ?? '') . '|' . $raw);
    }
}