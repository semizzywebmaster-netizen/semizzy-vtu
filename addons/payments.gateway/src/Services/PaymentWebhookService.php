<?php

namespace Semizzy\Addons\Payments\Services;

use App\Models\ApiProvider;
use App\Models\WalletAccount;
use App\Models\WalletMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Semizzy\Addons\Payments\Models\PaymentIntent;
use Semizzy\Addons\Payments\Models\PaymentWebhookEvent;

final class PaymentWebhookService
{
    public function handle(ApiProvider $provider, array $payload, array $headers): PaymentWebhookEvent
    {
        $endpoint = $provider->endpoints()->where('enabled', true)->whereIn('operation', ['webhook','payment_webhook'])->latest('id')->first();
        $config = is_array($endpoint?->webhook_config) ? $endpoint->webhook_config : [];
        $secret = (string) ($config['secret'] ?? '');
        $signatureHeader = (string) ($config['signature_header'] ?? 'X-Webhook-Signature');
        $algorithm = strtolower((string) ($config['algorithm'] ?? 'sha256'));
        $provided = (string) ($headers[$signatureHeader] ?? $headers[strtolower($signatureHeader)] ?? '');
        $raw = request()->getContent();

        if (($config['require_signature'] ?? true) && ($secret === '' || $provided === '')) {
            throw new RuntimeException('Webhook signature is required but not configured or supplied.');
        }
        if ($secret !== '' && $provided !== '') {
            $prefix = (string) ($config['signature_prefix'] ?? '');
            $candidate = hash_hmac($algorithm, $raw, $secret);
            $providedValue = str_starts_with($provided, $prefix) ? substr($provided, strlen($prefix)) : $provided;
            if (! hash_equals($candidate, trim($providedValue))) {
                throw new RuntimeException('Invalid webhook signature.');
            }
        }

        $eventId = (string) data_get($payload, $config['event_id_path'] ?? 'id', '');
        if ($eventId === '') $eventId = hash('sha256', $raw);
        $reference = (string) data_get($payload, $config['reference_path'] ?? 'reference', data_get($payload, 'data.reference', ''));
        $eventType = (string) data_get($payload, $config['event_type_path'] ?? 'event', 'payment');
        $signatureHash = hash('sha256', $provided ?: $raw);

        $event = PaymentWebhookEvent::firstOrCreate(
            ['provider_key' => (string) $provider->identifier, 'event_id' => $eventId],
            ['event_type'=>$eventType,'signature_hash'=>$signatureHash,'processing_status'=>'received','payment_reference'=>$reference ?: null,'payload'=>$payload]
        );
        if ($event->processed_at !== null) return $event;

        DB::transaction(function () use ($event, $provider, $payload, $reference, $config): void {
            $event->update(['processing_status'=>'processing','payment_reference'=>$reference ?: null,'payload'=>$payload]);
            $payment = PaymentIntent::query()->where('reference',$reference)->lockForUpdate()->first();
            if (!$payment) throw new RuntimeException('Payment reference was not found.');
            if ($payment->status === 'paid') {
                $event->update(['processing_status'=>'processed','processed_at'=>now(),'processing_error'=>null]);
                return;
            }

            $status = strtolower((string) data_get($payload, $config['status_path'] ?? 'status', data_get($payload,'data.status','')));
            if (!in_array($status, ['success','successful','completed','complete','paid','approved'], true)) {
                $event->update(['processing_status'=>'ignored','processed_at'=>now(),'processing_error'=>'Webhook did not report a successful payment.']);
                return;
            }

            $payloadCurrency = strtoupper((string) data_get($payload, $config['currency_path'] ?? 'currency', data_get($payload, 'data.currency', '')));
            if ($payloadCurrency !== '' && $payloadCurrency !== strtoupper((string) $payment->currency)) {
                throw new RuntimeException('Payment currency does not match the payment intent.');
            }

            $payloadAmount = data_get($payload, $config['amount_path'] ?? 'amount_minor', data_get($payload, 'data.amount_minor'));
            if ($payloadAmount !== null && (string) $payloadAmount !== (string) $payment->amount_minor) {
                throw new RuntimeException('Payment amount does not match the payment intent.');
            }

            $wallet = WalletAccount::query()->whereKey($payment->wallet_account_id)->lockForUpdate()->first();
            if (!$wallet || $wallet->status !== 'active') throw new RuntimeException('Payment wallet is unavailable.');
            if (strtoupper($wallet->currency) !== strtoupper($payment->currency)) throw new RuntimeException('Payment currency does not match wallet currency.');

            $existing = WalletMovement::query()->where('operation_key','payment:webhook:'.$event->id)->first();
            if (!$existing) {
                $before = (string) $wallet->available_minor;
                $amount = (string) $payment->amount_minor;
                $after = $this->add($before, $amount);
                $wallet->forceFill(['available_minor'=>$after])->saveOrFail();
                WalletMovement::create([
                    'wallet_account_id'=>$wallet->id,
                    'operation_key'=>'payment:webhook:'.$event->id,
                    'reference'=>'PAY-CREDIT-'.$event->id,
                    'type'=>'payment_funding',
                    'amount_minor'=>$amount,
                    'currency'=>$wallet->currency,
                    'available_before_minor'=>$before,
                    'available_after_minor'=>$after,
                    'held_before_minor'=>(string)$wallet->held_minor,
                    'held_after_minor'=>(string)$wallet->held_minor,
                    'metadata'=>['payment_intent_id'=>$payment->id,'provider_id'=>$provider->id,'provider_reference'=>$payment->provider_reference,'webhook_event_id'=>$event->id],
                ]);
            }
            $payment->forceFill(['status'=>'paid','paid_at'=>now(),'provider_id'=>$provider->id,'provider_reference'=>$payment->provider_reference ?: (string)data_get($payload,$config['provider_reference_path'] ?? 'provider_reference','')])->saveOrFail();
            $event->update(['processing_status'=>'processed','processed_at'=>now(),'processing_error'=>null]);
        });

        return $event->fresh();
    }

    private function add(string $a, string $b): string
    {
        $a=ltrim($a,'0')?:'0'; $b=ltrim($b,'0')?:'0';
        if (function_exists('bcadd')) return bcadd($a,$b,0);
        $carry=0; $out='';
        for($i=0,$j=0;$i<strlen($a)||$j<strlen($b);$i++,$j++){ $sum=($i<strlen($a)?ord($a[strlen($a)-1-$i])-48:0)+($j<strlen($b)?ord($b[strlen($b)-1-$j])-48:0)+$carry; $out=($sum%10).$out; $carry=intdiv($sum,10); }
        return $carry?($carry.$out):$out;
    }
}