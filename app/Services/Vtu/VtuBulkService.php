<?php

namespace App\Services\Vtu;

use App\Models\ServiceProduct;
use App\Models\VtuBulkOperation;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;

class VtuBulkService
{
    private const MAX_ITEMS = 500;

    public function __construct(
        private VtuTransactionService $transactions,
        private VtuPayloadValidator $validator,
    ) {}

    public function execute(int $uid, array $items, string $tier = 'USER', ?string $operationKey = null): VtuBulkOperation
    {
        if (count($items) < 1 || count($items) > self::MAX_ITEMS) {
            throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'A bulk request must contain between 1 and ' . self::MAX_ITEMS . ' items.']);
        }
        if ($operationKey !== null && (trim($operationKey) === '' || strlen($operationKey) > 160)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['idempotency_key' => 'The bulk idempotency key is invalid.']);
        }
        $items = array_values($items);
        $seenKeys = [];
        foreach ($items as $index => $item) {
            if (! is_array($item) || ! isset($item['product_id']) || ! is_numeric($item['product_id']) || (int) $item['product_id'] < 1) {
                throw \Illuminate\Validation\ValidationException::withMessages(['items.' . $index . '.product_id' => 'A valid product ID is required.']);
            }
            if (isset($item['idempotency_key'])) {
                if (! is_string($item['idempotency_key']) || trim($item['idempotency_key']) === '' || strlen($item['idempotency_key']) > 160) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['items.' . $index . '.idempotency_key' => 'The item idempotency key is invalid.']);
                }
                if (isset($seenKeys[$item['idempotency_key']])) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['items.' . $index . '.idempotency_key' => 'Item idempotency keys must be unique within a bulk request.']);
                }
                $seenKeys[$item['idempotency_key']] = true;
            }
            if (isset($item['payload']) && ! is_array($item['payload'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['items.' . $index . '.payload' => 'The item payload must be an object.']);
            }
        }
        $operationKey = $operationKey ?: 'vtu-bulk-' . Str::uuid();

        // Bind a caller-supplied bulk idempotency key to the exact normalized
        // request. This prevents replaying the same key with different work.
        $fingerprintPayload = array_map(
            static function (array $item): array {
                return [
                    'product_id' => (int) $item['product_id'],
                    'idempotency_key' => isset($item['idempotency_key']) ? (string) $item['idempotency_key'] : null,
                    'payload' => (array) ($item['payload'] ?? []),
                ];
            },
            $items
        );
        $fingerprint = hash('sha256', json_encode($fingerprintPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $existing = VtuBulkOperation::query()
            ->where('user_id', $uid)
            ->where('idempotency_key', $operationKey)
            ->first();

        if ($existing) {
            $metadata = (array) $existing->metadata;
            if (($metadata['request_fingerprint'] ?? null) !== $fingerprint ||
                (int) $existing->total_items !== count($items)) {
                throw new \RuntimeException('Bulk idempotency key has already been used for a different request.');
            }

            return $existing->load('items');
        }

        try {
            $bulk = VtuBulkOperation::create([
                'uuid' => (string) Str::uuid(),
                'reference' => 'BULK-' . strtoupper(Str::random(20)),
                'user_id' => $uid,
                'status' => 'processing',
                'total_items' => count($items),
                'idempotency_key' => $operationKey,
                'metadata' => ['request_fingerprint' => $fingerprint],
            ]);
        } catch (QueryException $e) {
            // Another concurrent request may have won the unique key race.
            // Only recover if the durable row proves this is the same request.
            $existing = VtuBulkOperation::query()
                ->where('user_id', $uid)
                ->where('idempotency_key', $operationKey)
                ->first();

            if (! $existing) {
                throw $e;
            }

            $metadata = (array) $existing->metadata;
            if (($metadata['request_fingerprint'] ?? null) !== $fingerprint ||
                (int) $existing->total_items !== count($items)) {
                throw new \RuntimeException('Bulk idempotency key has already been used for a different request.');
            }

            return $existing->load('items');
        }

        foreach (array_values($items) as $i => $item) {
            $key = (string) ($item['idempotency_key'] ?? ($bulk->reference . ':' . ($i + 1)));
            $payload = (array) ($item['payload'] ?? []);
            $row = $bulk->items()->create([
                'sequence' => $i + 1,
                'idempotency_key' => $key,
                'recipient' => $payload['recipient'] ?? $payload['phone'] ?? null,
                'product_id' => $item['product_id'],
                'status' => 'processing',
            ]);

            try {
                $product = ServiceProduct::query()->with('service')->findOrFail((int) $item['product_id']);
                if (! $product->service || ! $product->enabled || ! $product->service->enabled) {
                    throw new \RuntimeException('The selected VTU product or service is unavailable.');
                }
                $this->validator->validate($product->service, $payload);
                $transaction = $this->transactions->process(
                    $this->transactions->create($uid, $product, $payload, $tier, $key)
                );
                $row->update([
                    'vtu_transaction_id' => $transaction->id,
                    'status' => $transaction->status,
                    'amount_minor' => $transaction->total_minor,
                    'error_message' => $transaction->failure_message,
                ]);
                if ($transaction->status === 'successful') {
                    $bulk->increment('successful_items');
                } elseif (in_array($transaction->status, ['failed', 'reversed', 'cancelled'], true)) {
                    $bulk->increment('failed_items');
                }
            } catch (\Throwable $e) {
                Log::warning('VTU bulk item processing failed.', ['bulk_operation_id' => $bulk->id, 'bulk_item_id' => $row->id, 'user_id' => $uid, 'exception' => get_class($e)]);
                // A timeout or local exception can occur after the transaction
                // has been reserved. Reflect the durable transaction state rather
                // than incorrectly reporting a failed/refunded item.
                $transaction = \App\Models\VtuTransaction::query()
                    ->where('idempotency_key', $key)
                    ->where('user_id', $uid)
                    ->first();
                if ($transaction) {
                    $row->update([
                        'vtu_transaction_id' => $transaction->id,
                        'status' => $transaction->status,
                        'amount_minor' => $transaction->total_minor,
                        'error_message' => $transaction->failure_message,
                    ]);
                    if ($transaction->status === 'successful') {
                        $bulk->increment('successful_items');
                    } elseif (in_array($transaction->status, ['failed', 'reversed', 'cancelled'], true)) {
                        $bulk->increment('failed_items');
                    }
                } else {
                    $row->update(['status' => 'failed', 'error_message' => 'This item could not be processed. Contact support with the bulk reference.']);
                    $bulk->increment('failed_items');
                }
            }
            $bulk->increment('processed_items');
        }

        $bulk->refresh();
        $pending = max(0, $bulk->total_items - $bulk->successful_items - $bulk->failed_items);
        $bulk->status = match (true) {
            $pending > 0 => 'pending',
            $bulk->failed_items > 0 && $bulk->successful_items > 0 => 'partial',
            $bulk->failed_items > 0 => 'failed',
            default => 'successful',
        };
        $bulk->metadata = array_merge((array) $bulk->metadata, ['pending_items' => $pending]);
        $bulk->save();

        return $bulk->load('items');
    }
}
