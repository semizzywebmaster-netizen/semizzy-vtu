<?php

namespace App\Jobs;

use App\Models\CommunicationDeliveryLog;
use App\Services\Communication\CommunicationProviderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCommunicationProviderDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];
    public int $timeout = 60;

    public function __construct(public int $deliveryLogId)
    {
        $this->onQueue('notifications');
    }

    public function handle(CommunicationProviderService $providers): void
    {
        $log = CommunicationDeliveryLog::query()->find($this->deliveryLogId);

        if (!$log || !in_array($log->status, ['queued', 'retrying'], true)) {
            return;
        }

        $user = $log->user;
        $recipient = trim((string) ($user?->phone ?? ''));

        if ($recipient === '') {
            $log->forceFill([
                'status' => 'skipped',
                'error_message' => 'Recipient has no phone number configured.',
                'failed_at' => now(),
                'attempts' => (int) $log->attempts + 1,
            ])->save();

            return;
        }

        $log->forceFill([
            'status' => 'processing',
            'attempts' => (int) $log->attempts + 1,
        ])->save();

        try {
            $result = $providers->send(
                (string) $log->channel,
                $recipient,
                (string) ($log->campaign?->message ?? ''),
                [
                    'campaign_id' => $log->campaign_id,
                    'delivery_log_id' => $log->id,
                ]
            );
        } catch (\Throwable $e) {
            report($e);
            $log->forceFill([
                'status' => 'failed',
                'error_message' => 'Communication provider could not be reached or is not configured.',
                'failed_at' => now(),
            ])->save();

            return;
        }

        if ($result['accepted'] === true) {
            $log->forceFill([
                'status' => 'sent',
                'provider_id' => $result['provider_id'],
                'provider_reference' => $result['provider_reference'],
                'error_message' => null,
                'sent_at' => now(),
                'failed_at' => null,
            ])->save();

            return;
        }

        if (($result['duplicate_risk'] ?? false) === true || in_array($result['status'] ?? '', ['UNKNOWN', 'PENDING'], true)) {
            $log->forceFill([
                'status' => 'unknown',
                'provider_id' => $result['provider_id'],
                'provider_reference' => $result['provider_reference'],
                'error_message' => 'Provider outcome is uncertain; automatic retry was blocked to prevent duplicate delivery.',
                'failed_at' => null,
            ])->save();

            return;
        }

        if (($result['retryable'] ?? false) === true && $this->attempts() < $this->tries) {
            $log->forceFill([
                'status' => 'retrying',
                'provider_id' => $result['provider_id'],
                'provider_reference' => $result['provider_reference'],
                'error_message' => 'Temporary provider failure; delivery will be retried safely.',
                'failed_at' => null,
            ])->save();

            $this->release($this->backoff[min($this->attempts() - 1, count($this->backoff) - 1)]);
            return;
        }

        $log->forceFill([
            'status' => 'failed',
            'provider_id' => $result['provider_id'],
            'provider_reference' => $result['provider_reference'],
            'error_message' => 'Provider rejected the communication request.',
            'failed_at' => now(),
        ])->save();
    }

    public function failed(\Throwable $exception): void
    {
        CommunicationDeliveryLog::query()
            ->whereKey($this->deliveryLogId)
            ->whereIn('status', ['queued', 'processing', 'retrying'])
            ->update([
                'status' => 'failed',
                'error_message' => 'Queued communication delivery exhausted its retry attempts.',
                'failed_at' => now(),
            ]);
    }
}
