<?php

namespace App\Services\Cac;

use App\Models\CacOrder;
use App\Models\CacOrderStatusHistory;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class CacOrderProcessor
{
    public function __construct(private CacProviderGateway $gateway, private AuditLogger $audit) {}

    public function process(CacOrder $order): CacOrder
    {
        $claimed = DB::transaction(function () use ($order) {
            $locked = CacOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (!in_array($locked->status, ['provider_ready', 'processing'], true)) {
                return false;
            }

            $meta = (array) $locked->metadata;
            if (($meta['provider_claimed'] ?? false) === true) {
                return false;
            }

            $meta['provider_claimed'] = true;
            $meta['provider_claimed_at'] = now()->toIso8601String();
            $locked->metadata = $meta;
            $from = $locked->status;
            $locked->status = 'processing';
            $locked->submitted_at = now();
            $locked->save();

            if ($from !== 'processing') {
                $locked->statusHistory()->create([
                    'from_status' => $from,
                    'to_status' => 'processing',
                    'source' => 'system',
                    'reason' => 'Provider execution claimed.',
                ]);
            }

            return true;
        });

        if (!$claimed) {
            return $order->fresh();
        }

        try {
            $result = $this->gateway->submit($order->fresh('product'));
        } catch (\Throwable $e) {
            return $this->markUnknownAfterProviderException($order, $e);
        }

        return DB::transaction(function () use ($order, $result) {
            $locked = CacOrder::query()->lockForUpdate()->findOrFail($order->id);
            $number = (int) $locked->attempts()->max('attempt_number') + 1;

            $locked->attempts()->create([
                'api_provider_id' => $result->providerId,
                'attempt_number' => $number,
                'operation' => 'transaction_initiation',
                'status' => $result->status,
                'provider_reference' => $result->providerReference,
                'request_payload' => $locked->request_payload,
                'response_payload' => $this->safeProviderData($result->data),
                'error_message' => $result->message,
            ]);

            $locked->api_provider_id = $result->providerId ?: $locked->api_provider_id;
            $locked->provider_reference = $result->providerReference ?: $locked->provider_reference;
            $locked->response_payload = $this->safeProviderData($result->data);

            $meta = (array) $locked->metadata;
            unset($meta['provider_claimed'], $meta['provider_claimed_at']);
            $locked->metadata = $meta;

            $from = $locked->status;
            if ($result->accepted) {
                $locked->status = 'completed';
                $locked->completed_at = now();
            } elseif ($result->duplicateRisk || strtoupper($result->status) === 'UNKNOWN') {
                $locked->status = 'pending_requery';
                $locked->failure_message = $result->message;
            } elseif (in_array(strtoupper($result->status), ['PENDING', 'PROCESSING'], true)) {
                $locked->status = 'processing';
            } else {
                $locked->status = 'failed';
                $locked->failure_message = $result->message ?: 'CAC provider rejected the request.';
                $locked->completed_at = now();
            }

            $locked->save();

            if ($from !== $locked->status) {
                $locked->statusHistory()->create([
                    'from_status' => $from,
                    'to_status' => $locked->status,
                    'source' => 'provider',
                    'reason' => $result->message,
                ]);
            }

            $this->audit->record('cac.order.provider_processed', $locked, [
                'status' => $locked->status,
                'provider_id' => $locked->api_provider_id,
                'attempt_number' => $number,
            ]);

            return $locked->fresh();
        });
    }

    private function safeProviderData(mixed $data): ?array
    {
        if (!is_array($data)) {
            return null;
        }

        $redact = function (mixed $value, string $key = '') use (&$redact): mixed {
            if (preg_match('/token|secret|password|authorization|credential|api[_-]?key/i', $key)) {
                return '[REDACTED]';
            }
            if (is_array($value)) {
                $out = [];
                foreach ($value as $childKey => $childValue) {
                    $out[$childKey] = $redact($childValue, (string) $childKey);
                }
                return $out;
            }
            return is_scalar($value) || $value === null ? $value : null;
        };

        return $redact($data);
    }

    private function markUnknownAfterProviderException(CacOrder $order, \Throwable $exception): CacOrder
    {
        return DB::transaction(function () use ($order, $exception) {
            $locked = CacOrder::query()->lockForUpdate()->findOrFail($order->id);
            $from = $locked->status;
            $number = (int) $locked->attempts()->max('attempt_number') + 1;

            $locked->attempts()->create([
                'api_provider_id' => $locked->api_provider_id,
                'attempt_number' => $number,
                'operation' => 'transaction_initiation',
                'status' => 'UNKNOWN',
                'provider_reference' => $locked->provider_reference,
                'request_payload' => $locked->request_payload,
                'response_payload' => null,
                'error_message' => 'Provider execution failed before a definitive result was received.',
            ]);

            $meta = (array) $locked->metadata;
            unset($meta['provider_claimed'], $meta['provider_claimed_at']);
            $locked->metadata = $meta;
            $locked->status = 'pending_requery';
            $locked->failure_message = 'Provider execution returned no definitive result.';
            $locked->save();

            if ($from !== 'pending_requery') {
                $locked->statusHistory()->create([
                    'from_status' => $from,
                    'to_status' => 'pending_requery',
                    'source' => 'provider',
                    'reason' => 'Provider execution exception; reconciliation required.',
                ]);
            }

            $this->audit->record('cac.order.provider_exception', $locked, [
                'status' => 'pending_requery',
                'attempt_number' => $number,
                'exception' => get_class($exception),
            ]);

            return $locked->fresh();
        });
    }
}
