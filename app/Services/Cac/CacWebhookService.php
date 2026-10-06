<?php

namespace App\Services\Cac;

use App\Models\ApiProvider;
use App\Models\CacOrder;
use App\Models\CacWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CacWebhookService
{
    public function handle(Request $request, ApiProvider $provider): array
    {
        $raw = $request->getContent();
        $credentials = (array) ($provider->credentials ?? []);
        $secret = $credentials['webhook_secret'] ?? $credentials['webhookSecret'] ?? null;
        $signature = $request->header('X-Webhook-Signature') ?? $request->header('X-Signature');

        if (!$secret || !$signature) {
            throw new RuntimeException('Webhook authentication failed.');
        }

        $provided = trim((string) $signature);
        if (str_starts_with($provided, 'sha256=')) {
            $provided = substr($provided, 7);
        }

        $expected = hash_hmac('sha256', $raw, (string) $secret);
        if (!hash_equals($expected, $provided)) {
            throw new RuntimeException('Webhook authentication failed.');
        }

        $payload = $request->json()->all();
        if (!is_array($payload)) {
            throw new RuntimeException('Invalid webhook payload.');
        }

        $eventId = trim((string) ($request->header('X-Webhook-Id') ?? $payload['event_id'] ?? $payload['id'] ?? ''));
        if ($eventId === '' || strlen($eventId) > 190) {
            throw new RuntimeException('Invalid webhook event.');
        }

        $eventType = substr((string) ($payload['event_type'] ?? $payload['type'] ?? 'cac.order.status'), 0, 120);
        $reference = trim((string) ($payload['reference'] ?? $payload['transaction_reference'] ?? $payload['order_reference'] ?? ''));
        $status = strtoupper(trim((string) ($payload['status'] ?? $payload['transaction_status'] ?? '')));

        $safePayload = array_filter([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'reference' => $reference ?: null,
            'status' => $status ?: null,
            'provider_reference' => isset($payload['provider_reference']) ? (string) $payload['provider_reference'] : null,
            'code' => isset($payload['code']) ? substr((string) $payload['code'], 0, 100) : null,
            'message' => isset($payload['message']) ? substr((string) $payload['message'], 0, 500) : null,
        ], fn ($value) => $value !== null && $value !== '');

        $signatureFingerprint = hash('sha256', $provided);

        return DB::transaction(function () use ($provider, $eventId, $eventType, $signatureFingerprint, $safePayload, $reference, $status) {
            $event = CacWebhookEvent::firstOrCreate(
                ['api_provider_id' => $provider->id, 'event_id' => $eventId],
                [
                    'event_type' => $eventType,
                    'signature' => $signatureFingerprint,
                    'order_reference' => $reference ?: null,
                    'payload' => $safePayload,
                    'status' => 'received',
                ]
            );

            if (!$event->wasRecentlyCreated) {
                if ($event->status === 'processed') {
                    return ['duplicate' => true, 'processed' => true];
                }

                if ($event->status === 'ignored') {
                    return ['duplicate' => true, 'processed' => false];
                }
            }

            $order = $reference !== ''
                ? CacOrder::query()
                    ->where(function ($query) use ($reference) {
                        $query->where('reference', $reference)->orWhere('provider_reference', $reference);
                    })
                    ->lockForUpdate()
                    ->first()
                : null;

            if (!$order) {
                $event->update([
                    'status' => 'ignored',
                    'error_message' => 'CAC order reference not found',
                    'processed_at' => now(),
                ]);

                return ['duplicate' => false, 'processed' => false];
            }

            if ($order->api_provider_id !== null && (int) $order->api_provider_id !== (int) $provider->id) {
                $event->update([
                    'status' => 'ignored',
                    'error_message' => 'Provider does not own this CAC order',
                    'processed_at' => now(),
                ]);

                return ['duplicate' => false, 'processed' => false];
            }

            $map = [
                'SUCCESS' => 'completed',
                'SUCCESSFUL' => 'completed',
                'COMPLETED' => 'completed',
                'ACCEPTED' => 'completed',
                'PENDING' => 'pending_requery',
                'PROCESSING' => 'processing',
                'FAILED' => 'failed',
                'FAILURE' => 'failed',
                'REJECTED' => 'failed',
            ];

            $to = $map[$status] ?? null;
            if (!$to) {
                $event->update([
                    'status' => 'ignored',
                    'error_message' => 'Unsupported provider status',
                    'processed_at' => now(),
                ]);

                return ['duplicate' => false, 'processed' => false];
            }

            $from = (string) $order->status;
            $allowed = [
                'provider_ready' => ['processing', 'pending_requery', 'completed', 'failed'],
                'processing' => ['processing', 'pending_requery', 'completed', 'failed'],
                'pending_requery' => ['processing', 'pending_requery', 'completed', 'failed'],
            ];

            if ($from === $to) {
                // Idempotent status callback.
            } elseif (!in_array($to, $allowed[$from] ?? [], true)) {
                $event->update([
                    'status' => 'ignored',
                    'error_message' => 'Invalid CAC order status transition',
                    'processed_at' => now(),
                ]);

                return ['duplicate' => false, 'processed' => false];
            } else {
                $order->update([
                    'status' => $to,
                    'completed_at' => $to === 'completed' ? now() : $order->completed_at,
                    'failure_message' => $to === 'failed'
                        ? ($safePayload['message'] ?? 'Provider reported failure')
                        : $order->failure_message,
                ]);

                $order->statusHistory()->create([
                    'from_status' => $from,
                    'to_status' => $to,
                    'source' => 'webhook',
                    'reason' => 'Provider webhook update',
                    'metadata' => [
                        'event_id' => $eventId,
                        'provider_id' => $provider->id,
                    ],
                ]);
            }

            if (!empty($safePayload['provider_reference']) && !$order->provider_reference) {
                $order->update([
                    'provider_reference' => $safePayload['provider_reference'],
                    'api_provider_id' => $provider->id,
                ]);
            }

            $event->update([
                'status' => 'processed',
                'processed_at' => now(),
                'error_message' => null,
            ]);

            return ['duplicate' => false, 'processed' => true, 'status' => $to];
        });
    }
}
