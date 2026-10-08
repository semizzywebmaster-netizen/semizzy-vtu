<?php

namespace Semizzy\Addons\Payments\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Semizzy\Addons\Payments\Models\PaymentGatewayProvider;
use Semizzy\Addons\Payments\Models\PaymentIntent;
use Semizzy\Addons\Payments\Models\PaymentWebhookEvent;

final class PaymentWebhookService
{
    public function __construct(private PaymentGatewayManager $gateways) {}

    public function handle(PaymentGatewayProvider $provider, array $payload, array $headers): PaymentWebhookEvent
    {
        $raw = request()->getContent();
        $config = is_array($provider->settings) ? $provider->settings : [];
        $secret = (string) ($provider->credentials['webhook_secret'] ?? $provider->credentials['secret_key'] ?? $provider->credentials['secret'] ?? '');
        $signature = $this->header($headers, (string) ($config['signature_header'] ?? ($provider->driver === 'paystack' ? 'x-paystack-signature' : 'monnify-signature')));

        if (($config['require_webhook_signature'] ?? true) && ($secret === '' || $signature === '')) {
            throw new RuntimeException('Webhook signature is required but not configured or supplied.');
        }

        if ($secret !== '' && $signature !== '') {
            $candidate = hash_hmac('sha512', $raw, $secret);
            if (!hash_equals(strtolower(trim($candidate)), strtolower(trim($signature)))) {
                throw new RuntimeException('Invalid payment gateway webhook signature.');
            }
        }

        $reference = $this->reference($provider, $payload);
        if ($reference === '') throw new RuntimeException('Payment reference was not found in webhook payload.');

        $eventId = $this->eventId($provider, $payload, $raw, $reference);
        $eventType = $this->eventType($provider, $payload);
        $event = PaymentWebhookEvent::firstOrCreate(
            ['provider_key' => $provider->code, 'event_id' => $eventId],
            [
                'event_type' => $eventType,
                'signature_hash' => hash('sha256', $signature ?: $raw),
                'processing_status' => 'received',
                'payment_reference' => $reference,
                'payload' => $payload,
            ]
        );

        if ($event->processed_at !== null) return $event;

        $verified = $this->gateways->adapter($provider)->verifyCollection($provider, $reference);
        $verifiedStatus = $this->verifiedStatus($provider, $verified);
        $verifiedAmountMinor = $this->verifiedAmountMinor($provider, $verified);
        $verifiedCurrency = $this->verifiedCurrency($provider, $verified);
        if (!in_array($verifiedStatus, ['success','successful','completed','complete','paid','approved'], true)) {
            throw new RuntimeException('Gateway verification did not confirm a successful payment.');
        }

        DB::transaction(function () use ($event, $provider, $payload, $reference, $verifiedAmountMinor, $verifiedCurrency): void {
            $event->update(['processing_status' => 'processing', 'payload' => $payload]);

            $payment = PaymentIntent::query()->where('reference', $reference)->lockForUpdate()->first();
            if (!$payment) throw new RuntimeException('Payment reference was not found.');

            if ($payment->status === 'paid') {
                $event->update(['processing_status' => 'processed', 'processed_at' => now(), 'processing_error' => null]);
                return;
            }

            $status = $this->status($provider, $payload);
            if (!in_array($status, ['success','successful','completed','complete','paid','approved'], true)) {
                $event->update(['processing_status' => 'ignored', 'processed_at' => now(), 'processing_error' => 'Webhook did not report a successful payment.']);
                return;
            }

            $amountMinor = $verifiedAmountMinor ?? $this->amountMinor($provider, $payload);
            if ($amountMinor !== null && $amountMinor !== (string) $payment->amount_minor) {
                throw new RuntimeException('Payment amount does not match the payment intent.');
            }

            $currency = strtoupper($verifiedCurrency ?: $this->currency($provider, $payload));
            if ($currency !== '' && $currency !== strtoupper((string) $payment->currency)) {
                throw new RuntimeException('Payment currency does not match the payment intent.');
            }

            $payment->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
                'provider_id' => $provider->id,
                'provider_reference' => $this->providerReference($provider, $payload) ?: $payment->provider_reference,
            ])->saveOrFail();

            app(\App\Services\Finance\WalletCreditService::class)->credit(
                $payment->user()->firstOrFail(),
                $this->majorFromMinor((string) $payment->amount_minor),
                'payment:intent:'.$payment->id,
                'PAY-CREDIT-'.$event->id,
                'payment_funding',
                [
                    'payment_intent_id' => $payment->id,
                    'provider_id' => $provider->id,
                    'provider_reference' => $payment->provider_reference,
                    'webhook_event_id' => $event->id,
                    'currency' => $payment->currency,
                ]
            );

            $event->update(['processing_status' => 'processed', 'processed_at' => now(), 'processing_error' => null]);
        });

        return $event->fresh();
    }

    private function verifiedStatus(PaymentGatewayProvider $provider, array $data): string
    {
        return strtolower((string) match ($provider->driver) {
            'paystack' => data_get($data, 'status', ''),
            'monnify' => data_get($data, 'paymentStatus', ''),
            default => data_get($data, 'status', ''),
        });
    }

    private function verifiedAmountMinor(PaymentGatewayProvider $provider, array $data): ?string
    {
        $amount = match ($provider->driver) {
            'paystack' => data_get($data, 'amount'),
            'monnify' => data_get($data, 'amountPaid', data_get($data, 'totalPayable')),
            default => data_get($data, 'amount'),
        };
        if ($amount === null || $amount === '') return null;
        return $provider->driver === 'monnify' ? number_format((float) $amount * 100, 0, '.', '') : (string) $amount;
    }

    private function verifiedCurrency(PaymentGatewayProvider $provider, array $data): string
    {
        return strtoupper((string) match ($provider->driver) {
            'monnify' => data_get($data, 'currencyCode', ''),
            default => data_get($data, 'currency', ''),
        });
    }

    private function majorFromMinor(string $minor): string
    {
        $minor = ltrim($minor, '0') ?: '0';
        if (strlen($minor) === 1) return '0.0'.$minor;
        if (strlen($minor) === 2) return '0.'.$minor;
        return substr($minor, 0, -2).'.'.substr($minor, -2);
    }

    private function header(array $headers, string $name): string
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === strtolower($name)) return is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
        }
        return '';
    }

    private function config(PaymentGatewayProvider $provider): array
    {
        return is_array($provider->settings) ? $provider->settings : [];
    }

    private function reference(PaymentGatewayProvider $provider, array $payload): string
    {
        return match ($provider->driver) {
            'paystack' => (string) data_get($payload, 'data.reference', data_get($payload, 'reference', '')),
            'monnify' => (string) data_get($payload, 'eventData.paymentReference', ''),
            default => (string) data_get($payload, 'data.reference', data_get($payload, 'reference', '')),
        };
    }

    private function eventId(PaymentGatewayProvider $provider, array $payload, string $raw, string $reference): string
    {
        return match ($provider->driver) {
            'paystack' => (string) data_get($payload, 'data.id', hash('sha256', $raw)),
            'monnify' => (string) data_get($payload, 'eventData.transactionReference', hash('sha256', $raw)),
            default => hash('sha256', $provider->code.'|'.$reference.'|'.$raw),
        };
    }

    private function eventType(PaymentGatewayProvider $provider, array $payload): string
    {
        return (string) data_get($payload, 'event', data_get($payload, 'eventType', 'payment'));
    }

    private function status(PaymentGatewayProvider $provider, array $payload): string
    {
        return match ($provider->driver) {
            'paystack' => strtolower((string) data_get($payload, 'data.status', data_get($payload, 'status', ''))),
            'monnify' => strtolower((string) data_get($payload, 'eventData.paymentStatus', data_get($payload, 'eventData.paymentStatus', ''))),
            default => strtolower((string) data_get($payload, 'data.status', data_get($payload, 'status', ''))),
        };
    }

    private function amountMinor(PaymentGatewayProvider $provider, array $payload): ?string
    {
        $amount = match ($provider->driver) {
            'paystack' => data_get($payload, 'data.amount'),
            'monnify' => data_get($payload, 'eventData.amountPaid'),
            default => data_get($payload, 'data.amount', data_get($payload, 'amount')),
        };
        if ($amount === null || $amount === '') return null;
        if ($provider->driver === 'monnify') {
            return number_format((float) $amount * 100, 0, '.', '');
        }
        return (string) $amount;
    }

    private function currency(PaymentGatewayProvider $provider, array $payload): string
    {
        return strtoupper((string) match ($provider->driver) {
            'monnify' => data_get($payload, 'eventData.currency', ''),
            default => data_get($payload, 'data.currency', data_get($payload, 'currency', '')),
        });
    }

    private function providerReference(PaymentGatewayProvider $provider, array $payload): string
    {
        return (string) match ($provider->driver) {
            'paystack' => data_get($payload, 'data.id', ''),
            'monnify' => data_get($payload, 'eventData.transactionReference', ''),
            default => data_get($payload, 'data.id', ''),
        };
    }
}
