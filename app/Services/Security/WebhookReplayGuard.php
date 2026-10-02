<?php

namespace App\Services\Security;

use App\Models\ApiProvider;
use App\Models\WebhookReceipt;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class WebhookReplayGuard
{
    /**
     * Atomically claim a provider webhook event for processing.
     *
     * The caller must supply the provider's stable event identifier. The raw
     * payload is never persisted by this core security primitive.
     */
    public function claim(
        ApiProvider $provider,
        string $eventId,
        string $payload,
        ?string $signature = null
    ): WebhookReceipt {
        $eventId = trim($eventId);

        if ($eventId === '' || mb_strlen($eventId) > 191) {
            throw new RuntimeException('A stable webhook event ID is required.');
        }

        $payloadHash = hash('sha256', $payload);
        $signatureHash = $signature !== null && $signature !== ''
            ? hash('sha256', $signature)
            : null;

        try {
            return DB::transaction(function () use ($provider, $eventId, $payloadHash, $signatureHash): WebhookReceipt {
                return WebhookReceipt::create([
                    'api_provider_id' => $provider->getKey(),
                    'event_id' => $eventId,
                    'payload_hash' => $payloadHash,
                    'signature_hash' => $signatureHash,
                    'status' => 'received',
                    'received_at' => now(),
                ]);
            });
        } catch (QueryException $e) {
            if ($this->isDuplicateKey($e)) {
                $existing = WebhookReceipt::query()
                    ->where('api_provider_id', $provider->getKey())
                    ->where('event_id', $eventId)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            throw $e;
        }
    }

    public function markProcessed(WebhookReceipt $receipt): WebhookReceipt
    {
        if ($receipt->status === 'processed') {
            return $receipt;
        }

        $receipt->forceFill([
            'status' => 'processed',
            'processed_at' => now(),
            'processing_error' => null,
        ])->save();

        return $receipt->refresh();
    }

    public function markFailed(WebhookReceipt $receipt, string $error): WebhookReceipt
    {
        $safeError = trim($error);
        $safeError = $safeError === '' ? 'Webhook processing failed.' : $safeError;

        $receipt->forceFill([
            'status' => 'failed',
            'processing_error' => Str::limit($safeError, 2000, ''),
        ])->save();

        return $receipt->refresh();
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'unique')
            || str_contains($message, 'duplicate')
            || str_contains($message, 'constraint');
    }
}
