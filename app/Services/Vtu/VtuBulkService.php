<?php

namespace App\Services\Vtu;

use App\Models\ServiceProduct;
use App\Models\VtuBulkOperation;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

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
            throw \\Illuminate\\Validation\\ValidationException::withMessages(['items' => 'A bulk request must contain between 1 and ' . self::MAX_ITEMS . ' items.']);
        }
        if ($operationKey !== null && (trim($operationKey) === '' || strlen($operationKey) > 160)) {
            throw \\Illuminate\\Validation\\ValidationException::withMessages(['idempotency_key' => 'The bulk idempotency key is invalid.']);
        }
        $operationKey = $operationKey ?: 'vtu-bulk-' . Str::uuid();
        $existing = VtuBulkOperation::query()->where('user_id', $uid)->where('idempotency_key', $operationKey)->first();

        if ($existing) {
            return $existing->load('items');
        }

        $bulk = VtuBulkOperation::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'BULK-' . strtoupper(Str::random(20)),
            'user_id' => $uid,
            'status' => 'processing',
            'total_items' => count($items),
            'idempotency_key' => $operationKey,
        ]);

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
                $row->update(['status' => 'failed', 'error_message' => 'This item could not be processed. Contact support with the bulk reference.']);
                $bulk->increment('failed_items');
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
