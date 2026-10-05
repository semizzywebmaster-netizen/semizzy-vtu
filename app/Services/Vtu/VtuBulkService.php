<?php

namespace App\Services\Vtu;

use App\Models\ServiceProduct;
use App\Models\VtuBulkOperation;
use App\Models\VtuTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            throw \Illuminate\Validation\ValidationException::withMessages([
                'items' => 'A bulk request must contain between 1 and ' . self::MAX_ITEMS . ' items.',
            ]);
        }

        if ($operationKey !== null && (trim($operationKey) === '' || strlen($operationKey) > 160)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'idempotency_key' => 'The bulk idempotency key is invalid.',
            ]);
        }

        $items = array_values($items);
        $seenKeys = [];

        foreach ($items as $index => $item) {
            if (!is_array($item) || !isset($item['product_id']) || !is_numeric($item['product_id']) || (int) $item['product_id'] < 1) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items.' . $index . '.product_id' => 'A valid product ID is required.',
                ]);
            }

            if (isset($item['idempotency_key'])) {
                if (!is_string($item['idempotency_key']) || trim($item['idempotency_key']) === '' || strlen($item['idempotency_key']) > 160) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'items.' . $index . '.idempotency_key' => 'The item idempotency key is invalid.',
                    ]);
                }

                if (isset($seenKeys[$item['idempotency_key']])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'items.' . $index . '.idempotency_key' => 'Item idempotency keys must be unique within a bulk request.',
                    ]);
                }

                $seenKeys[$item['idempotency_key']] = true;
            }

            if (isset($item['payload']) && !is_array($item['payload'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items.' . $index . '.payload' => 'The item payload must be an object.',
                ]);
            }
        }

        $operationKey ??= 'vtu-bulk-' . Str::uuid();

        $fingerprintPayload = array_map(
            static fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'idempotency_key' => isset($item['idempotency_key']) ? (string) $item['idempotency_key'] : null,
                'payload' => (array) ($item['payload'] ?? []),
            ],
            $items,
        );

        $fingerprint = hash(
            'sha256',
            json_encode(
                $fingerprintPayload,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ),
        );

        $existing = VtuBulkOperation::query()
            ->where('user_id', $uid)
            ->where('idempotency_key', $operationKey)
            ->first();

        if ($existing) {
            $this->assertFingerprintMatches($existing, $fingerprint, count($items));

            if (in_array($existing->status, ['successful', 'failed', 'partial'], true)) {
                return $existing->load('items');
            }

            // A replay of an incomplete operation is a recovery/resume path.
            // Item-level transaction idempotency prevents duplicate provider fulfillment.
            $bulk = $existing->load('items');
        } else {
            try {
                $bulk = DB::transaction(function () use ($uid, $operationKey, $fingerprint, $items): VtuBulkOperation {
                    $bulk = VtuBulkOperation::create([
                        'uuid' => (string) Str::uuid(),
                        'reference' => 'BULK-' . strtoupper(Str::random(20)),
                        'user_id' => $uid,
                        'status' => 'processing',
                        'total_items' => count($items),
                        'idempotency_key' => $operationKey,
                        'metadata' => ['request_fingerprint' => $fingerprint],
                    ]);

                    foreach ($items as $i => $item) {
                        $key = (string) ($item['idempotency_key'] ?? ($bulk->reference . ':' . ($i + 1)));

                        $bulk->items()->create([
                            'sequence' => $i + 1,
                            'idempotency_key' => $key,
                            'recipient' => (($item['payload'] ?? [])['recipient'] ?? ($item['payload'] ?? [])['phone'] ?? null),
                            'product_id' => (int) $item['product_id'],
                            'status' => 'processing',
                        ]);
                    }

                    return $bulk;
                });
            } catch (QueryException $e) {
                if (!$this->isUniqueConstraintViolation($e)) {
                    throw $e;
                }

                $bulk = VtuBulkOperation::query()
                    ->where('user_id', $uid)
                    ->where('idempotency_key', $operationKey)
                    ->first();

                if (!$bulk) {
                    throw $e;
                }

                $this->assertFingerprintMatches($bulk, $fingerprint, count($items));

                if (in_array($bulk->status, ['successful', 'failed', 'partial'], true)) {
                    return $bulk->load('items');
                }

                $bulk->load('items');
            }
        }
    private const WORKER_LEASE_MINUTES = 15;

    private function acquireWorkerLease(VtuBulkOperation $bulk): array
    {
        return DB::transaction(function () use ($bulk): array {
            $locked = VtuBulkOperation::query()->lockForUpdate()->findOrFail($bulk->id);
            if (in_array($locked->status, ['successful', 'failed', 'partial'], true)) {
                return [false, null];
            }

            $metadata = (array) $locked->metadata;
            $until = $metadata['worker_lease_until'] ?? null;
            if ($until && now()->lt(\Illuminate\SupportCarbon::parse($until))) {
                return [false, null];
            }

            $token = (string) Str::uuid();
            $metadata['worker_lease_token'] = $token;
            $metadata['worker_lease_until'] = now()->addMinutes(self::WORKER_LEASE_MINUTES)->toIso8601String();
            $metadata['worker_lease_started_at'] = now()->toIso8601String();
            $locked->metadata = $metadata;
            $locked->status = 'processing';
            $locked->save();

            return [true, $token];
        });
    }

    private function heartbeatWorkerLease(VtuBulkOperation $bulk, string $token): void
    {
        $locked = VtuBulkOperation::query()->find($bulk->id);
        if (!$locked) {
            throw new \RuntimeException('Bulk operation no longer exists.');
        }

        $metadata = (array) $locked->metadata;
        if (($metadata['worker_lease_token'] ?? null) !== $token) {
            throw new \RuntimeException('Bulk worker lease was lost.');
        }

        $metadata['worker_lease_until'] = now()->addMinutes(self::WORKER_LEASE_MINUTES)->toIso8601String();
        $metadata['worker_heartbeat_at'] = now()->toIso8601String();
        $locked->metadata = $metadata;
        $locked->save();
    }

    private function releaseWorkerLease(VtuBulkOperation $bulk, string $token): void
    {
        $locked = VtuBulkOperation::query()->find($bulk->id);
        if (!$locked) {
            return;
        }

        $metadata = (array) $locked->metadata;
        if (($metadata['worker_lease_token'] ?? null) !== $token) {
            return;
        }

        unset($metadata['worker_lease_token'], $metadata['worker_lease_until']);
        $metadata['worker_released_at'] = now()->toIso8601String();
        $locked->metadata = $metadata;
        $locked->save();
    }

    public function recoverStaleOperations(int $limit = 50, int $staleMinutes = 10): int
    {
        $recovered = 0;
        $operations = VtuBulkOperation::query()
            ->where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes($staleMinutes))
            ->oldest('id')
            ->limit(max(1, min($limit, 100)))
            ->get();

        foreach ($operations as $bulk) {
            $metadata = (array) $bulk->metadata;
            if (!empty($metadata['worker_lease_until']) &&
                now()->lt(\Illuminate\SupportCarbon::parse($metadata['worker_lease_until']))) {
                continue;
            }

            $items = $bulk->items()->whereIn('status', ['processing', 'pending'])->get();
            foreach ($items as $item) {
                $key = (string) $item->idempotency_key;
                $transaction = VtuTransaction::query()
                    ->where('idempotency_key', $key)
                    ->where('user_id', $bulk->user_id)
                    ->first();

                if (!$transaction) {
                    if ($item->status === 'processing') {
                        // A worker can die after the durable item row is created but
                        // before VtuTransaction::create() commits. Keep the item
                        // resumable; marking it terminal would make an idempotent
                        // replay skip it permanently.
                        $item->update([
                            'status' => 'pending',
                            'error_message' => 'Bulk worker stopped before this transaction was created. The item is queued for safe resume using its idempotency key.',
                        ]);
                        $recovered++;
                    }
                    continue;
                }

                if (!$transaction->isTerminal()) {
                    $metadata = (array) $transaction->metadata;
                    if (($metadata['provider_initiation_claimed'] ?? false) === true &&
                        !empty($metadata['provider_initiation_claimed_at']) &&
                        now()->gte(\Illuminate\Support\Carbon::parse($metadata['provider_initiation_claimed_at'])->addMinutes($staleMinutes)) &&
                        !$transaction->provider_reference) {
                        $transaction = $this->transactions->recoverStaleInitiationClaim($transaction, $staleMinutes);
                    }
                }

                $this->syncItemFromTransaction($item, $transaction);
                $recovered++;
            }

            $metadata = array_merge((array) $bulk->metadata, [
                'last_recovery_at' => now()->toIso8601String(),
            ]);
            $bulk->metadata = $metadata;
            $bulk->save();
            $this->recalculate($bulk->fresh('items'));
        }

        return $recovered;
    }

    private function syncItemFromTransaction($row, VtuTransaction $transaction): void
    {
        $row->update([
            'vtu_transaction_id' => $transaction->id,
            'status' => $transaction->status,
            'amount_minor' => $transaction->total_minor,
            'error_message' => $transaction->failure_message,
        ]);
    }

    private function recalculate(VtuBulkOperation $bulk): VtuBulkOperation
    {
        $successful = $bulk->items()->where('status', 'successful')->count();
        $failed = $bulk->items()->whereIn('status', ['failed', 'reversed', 'cancelled'])->count();
        $pending = max(0, $bulk->total_items - $successful - $failed);

        $bulk->successful_items = $successful;
        $bulk->failed_items = $failed;
        $bulk->processed_items = $successful + $failed;
        $bulk->status = match (true) {
            $pending > 0 => 'pending',
            $failed > 0 && $successful > 0 => 'partial',
            $failed > 0 => 'failed',
            default => 'successful',
        };
        $bulk->metadata = array_merge((array) $bulk->metadata, [
            'pending_items' => $pending,
        ]);
        $bulk->save();

        return $bulk->fresh('items');
    }

    private function assertFingerprintMatches(VtuBulkOperation $bulk, string $fingerprint, int $itemCount): void
    {
        $metadata = (array) $bulk->metadata;

        if (($metadata['request_fingerprint'] ?? null) !== $fingerprint ||
            (int) $bulk->total_items !== $itemCount) {
            throw new \RuntimeException('Bulk idempotency key has already been used for a different request.');
        }
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        $driverCode = (string) ($e->errorInfo[1] ?? '');
        $message = strtolower($e->getMessage());

        return $sqlState === '23000' && (
            str_contains($message, 'unique') ||
            str_contains($message, 'duplicate') ||
            $driverCode === '1062'
        );
    }
}
