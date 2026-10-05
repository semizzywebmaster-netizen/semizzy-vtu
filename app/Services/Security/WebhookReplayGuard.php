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
    private const PROCESSING_LEASE_MINUTES = 10;

    public function claim(ApiProvider $provider, string $eventId, string $payload, ?string $signature = null): WebhookReceipt
    {
        $eventId = trim($eventId);

        if ($eventId === '' || mb_strlen($eventId) > 191) {
            throw new RuntimeException('A stable webhook event ID is required.');
        }

        $payloadHash = hash('sha256', $payload);
        $signatureHash = $signature !== null && $signature !== ''
            ? hash('sha256', $signature)
            : null;

        try {
            return DB::transaction(fn (): WebhookReceipt => WebhookReceipt::create([
                'api_provider_id' => $provider->getKey(),
                'event_id' => $eventId,
                'payload_hash' => $payloadHash,
                'signature_hash' => $signatureHash,
                'status' => 'received',
                'received_at' => now(),
            ]));
        } catch (QueryException $e) {
            if ($this->isDuplicateKey($e)) {
                $existing = WebhookReceipt::query()
                    ->where('api_provider_id', $provider->getKey())
                    ->where('event_id', $eventId)
                    ->first();

                if ($existing) {
                    if (! hash_equals($existing->payload_hash, $payloadHash)) {
                        throw new RuntimeException('Webhook event ID was already claimed with a different payload.');
                    }

                    if ($existing->signature_hash !== null && $signatureHash !== null && ! hash_equals($existing->signature_hash, $signatureHash)) {
                        throw new RuntimeException('Webhook event ID was already claimed with a different signature.');
                    }

                    return $existing;
                }
            }

            throw $e;
        }
    }

    public function beginProcessing(WebhookReceipt $receipt): bool
    {
        return DB::transaction(function () use ($receipt): bool {
            $locked = WebhookReceipt::query()->lockForUpdate()->findOrFail($receipt->id);

            if ($locked->status === 'processing') {
                $started = $locked->processing_started_at;
                if (! $started || now()->lt($started->copy()->addMinutes(self::PROCESSING_LEASE_MINUTES))) {
                    return false;
                }
            } elseif (! in_array($locked->status, ['received', 'failed'], true)) {
                return false;
            }

            $locked->forceFill([
                'status' => 'processing',
                'processing_started_at' => now(),
                'processing_token' => (string) Str::uuid(),
                'processing_error' => null,
            ])->save();

            return true;
        });
    }

    public function processingToken(WebhookReceipt $receipt): string
    {
        $token = trim((string) $receipt->processing_token);
        if ($token === '') {
            throw new RuntimeException('Webhook processing claim is missing.');
        }

        return $token;
    }

    public function markProcessed(WebhookReceipt $receipt, ?string $token = null): WebhookReceipt
    {
        return DB::transaction(function () use ($receipt, $token): WebhookReceipt {
            $locked = WebhookReceipt::query()->lockForUpdate()->findOrFail($receipt->id);
            if ($locked->status === 'processed') {
                return $locked;
            }
            if ($locked->status !== 'processing' || ($token !== null && ! hash_equals((string) $locked->processing_token, $token))) {
                return $locked;
            }

            $locked->forceFill([
                'status' => 'processed',
                'processing_started_at' => null,
                'processing_token' => null,
                'processed_at' => now(),
                'processing_error' => null,
            ])->save();

            return $locked->fresh();
        });
    }

    public function markFailed(WebhookReceipt $receipt, string $error, ?string $token = null): WebhookReceipt
    {
        $safeError = trim($error);
        $safeError = $safeError === '' ? 'Webhook processing failed.' : $safeError;
        $safeError = preg_replace('/(?:authorization|x-api-key|api[_-]?key|token|secret|password)\s*[:=]\s*[^,;]+/i', '$1: [REDACTED]', $safeError) ?? 'Webhook processing failed.';

        return DB::transaction(function () use ($receipt, $safeError, $token): WebhookReceipt {
            $locked = WebhookReceipt::query()->lockForUpdate()->findOrFail($receipt->id);
            if ($locked->status !== 'processing' || ($token !== null && ! hash_equals((string) $locked->processing_token, $token))) {
                return $locked;
            }

            $locked->forceFill([
                'status' => 'failed',
                'processing_started_at' => null,
                'processing_token' => null,
                'processing_error' => Str::limit($safeError, 2000, ''),
            ])->save();

            return $locked->fresh();
        });
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'unique')
            || str_contains($message, 'duplicate')
            || str_contains($message, 'constraint');
    }
}
