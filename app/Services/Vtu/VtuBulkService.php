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

    private function scheduleEditMinutes(): int
    {
        $value=(int)(\App\Models\SystemSetting::query()->where('key','vtu_schedule_edit_lock_minutes')->value('value') ?? 120);
        return in_array($value,[15,30,60,120,180,300,720,1440],true)?$value:120;
    }
    private function editUntil(\Carbon\CarbonInterface $scheduledAt): \Carbon\CarbonInterface
    {
        $delay=max(300,$scheduledAt->diffInSeconds(now(),false));
        $lock=min($this->scheduleEditMinutes()*60,max(300,(int)floor($delay/2)));
        return $scheduledAt->copy()->subSeconds($lock);
    }
    public function schedule(int $uid,array $items,string $tier,\Carbon\CarbonInterface $scheduledAt,?string $operationKey=null,?string $quoteFingerprint=null): VtuBulkOperation
    {
        if($scheduledAt->lte(now()->addMinutes(1))) throw new \RuntimeException('Scheduled time must be in the future.');
        if(count($items)<50||count($items)>self::MAX_ITEMS) throw new \RuntimeException('Scheduled airtime/data bulk purchase must contain between 50 and 500 recipients.');
        $operationKey ??= 'vtu-bulk-scheduled-'.Str::uuid();
        $quote=$this->quote($items,$tier);
        if($quoteFingerprint!==null && !hash_equals($quoteFingerprint,$quote['quote_fingerprint'])) throw new \RuntimeException('The bulk quote has expired or changed.');
        $existing=VtuBulkOperation::query()->where('user_id',$uid)->where('idempotency_key',$operationKey)->first();
        if($existing)return $existing->load('items');
        $editUntil=$this->editUntil($scheduledAt);
        $bulk=DB::transaction(function()use($uid,$items,$tier,$operationKey,$scheduledAt,$editUntil,$quote):VtuBulkOperation{
            $bulk=VtuBulkOperation::create(['uuid'=>(string)Str::uuid(),'reference'=>'BULK-'.strtoupper(Str::random(20)),'user_id'=>$uid,'status'=>'scheduled','scheduled_at'=>$scheduledAt,'edit_until'=>$editUntil,'total_items'=>count($items),'idempotency_key'=>$operationKey,'metadata'=>['scheduled'=>true,'quote_fingerprint'=>$quote['quote_fingerprint'],'pending_items'=>count($items)]]);
            foreach(array_values($items) as $i=>$item){
                $product=ServiceProduct::query()->with('service')->findOrFail((int)$item['product_id']);
                $payload=(array)($item['payload']??[]);
                $this->validator->validate($product->service,$payload);
                $key=(string)($item['idempotency_key']??($bulk->reference.':'.($i+1)));
                $tx=$this->transactions->create($uid,$product,$payload,$tier,$key);
                $bulk->items()->create(['sequence'=>$i+1,'idempotency_key'=>$key,'vtu_transaction_id'=>$tx->id,'recipient'=>$payload['recipient']??$payload['phone']??null,'product_id'=>$product->id,'amount_minor'=>$tx->total_minor,'status'=>'scheduled','metadata'=>['scheduled'=>true]]);
            }
            return $bulk;
        });
        return $bulk->fresh('items');
    }
    public function reschedule(int $uid,VtuBulkOperation $bulk,\Carbon\CarbonInterface $scheduledAt):VtuBulkOperation
    {
        if((int)$bulk->user_id!==$uid)abort(404);
        $bulk->load('items');
        if($bulk->status!=='scheduled')throw new \RuntimeException('Only scheduled operations can be edited.');
        if($bulk->edit_until && now()->gte($bulk->edit_until))throw new \RuntimeException('The editing window has closed.');
        if($scheduledAt->lte(now()->addMinutes(1)))throw new \RuntimeException('Scheduled time must be in the future.');
        if($scheduledAt->lte(now()->addMinutes(5)))throw new \RuntimeException('Leave at least 5 minutes for safe execution.');
        $bulk->scheduled_at=$scheduledAt;$bulk->edit_until=$this->editUntil($scheduledAt);$bulk->save();
        return $bulk->fresh('items');
    }

    public function quote(array $items, string $tier = 'USER'): array
    {
        if (count($items) < 1 || count($items) > self::MAX_ITEMS) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'items' => 'A bulk request must contain between 1 and ' . self::MAX_ITEMS . ' items.',
            ]);
        }

        $total = 0.0;
        $currency = null;
        $rows = [];

        foreach (array_values($items) as $index => $item) {
            if (!is_array($item) || !isset($item['product_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items.' . $index . '.product_id' => 'A valid product ID is required.',
                ]);
            }

            $product = ServiceProduct::query()->with('service')->findOrFail((int) $item['product_id']);
            if (!$product->service || !$product->enabled || !$product->service->enabled) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items.' . $index => 'The selected VTU product or service is unavailable.',
                ]);
            }

            $payload = (array) ($item['payload'] ?? []);
            $this->validator->validate($product->service, $payload);
            if (isset($payload['phone']) && is_string($payload['phone'])) {
                $network = strtolower(trim((string) ($payload['network'] ?? '')));
                if ($network === '') {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'items.' . $index . '.payload.network' => 'Network must be selected or resolved before a bulk quote.',
                    ]);
                }
            }
            $q = $this->transactions->quote($product, $tier);
            $price = (float) $q['customer_price'];

            if ($currency !== null && $currency !== $q['currency']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items.' . $index => 'All bulk items must use the same currency.',
                ]);
            }

            $currency = $q['currency'];
            $total += $price;
            $rows[] = [
                'index' => $index,
                'product_id' => (int) $product->id,
                'customer_price' => $q['customer_price'],
                'currency' => $q['currency'],
            ];
        }

        return [
            'total_customer_price' => number_format($total, 2, '.', ''),
            'currency' => $currency ?? 'NGN',
            'total_items' => count($rows),
            'items' => $rows,
            'quote_fingerprint' => $this->quoteFingerprint($items, $tier, $rows),
        ];
    }

    public function execute(int $uid, array $items, string $tier = 'USER', ?string $operationKey = null, ?string $quoteFingerprint = null): VtuBulkOperation
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

        $items = array_values($items);        $seenKeys = [];
        $seenRecipients = [];

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

            $product = ServiceProduct::query()->with('service')->find((int) $item['product_id']);
            if ($product?->service && in_array($product->service->key, ['airtime', 'data'], true)) {
                $phone = trim((string) (($item['payload'] ?? [])['phone'] ?? ''));
                if ($phone !== '') {
                    $canonical = preg_replace('/\D+/', '', $phone);
                    $canonical = preg_replace('/^234/', '0', $canonical);
                    if ($canonical !== '' && isset($seenRecipients[$canonical])) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'items.' . $index . '.payload.phone' => 'Duplicate recipient numbers are not allowed in a bulk airtime/data request.',
                        ]);
                    }
                    if ($canonical !== '') {
                        $seenRecipients[$canonical] = true;
                    }
                }
            }
        }

        if ($quoteFingerprint !== null && !preg_match('/^[a-f0-9]{64}$/', $quoteFingerprint)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'quote_fingerprint' => 'The bulk quote fingerprint is invalid.',
            ]);
        }

        if ($quoteFingerprint !== null) {
            $quote = $this->quote($items, $tier);
            if (!hash_equals($quoteFingerprint, $quote['quote_fingerprint'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'quote_fingerprint' => 'The bulk quote has expired or changed. Please request a new quote before purchasing.',
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
        } else {            try {
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

        [$leaseAcquired, $leaseToken] = $this->acquireWorkerLease($bulk);
        if (!$leaseAcquired) {
            return $bulk->fresh('items');
        }

        try {
            foreach ($items as $i => $item) {
                $this->heartbeatWorkerLease($bulk, $leaseToken);

                $row = $bulk->items->firstWhere('sequence', $i + 1);

                if (!$row) {
                    throw new \RuntimeException('Bulk operation item records are incomplete.');
                }

                if (in_array($row->status, ['successful', 'failed', 'reversed', 'cancelled'], true)) {
                    continue;
                }

                $key = (string) ($item['idempotency_key'] ?? ($bulk->reference . ':' . ($i + 1)));
                $payload = (array) ($item['payload'] ?? []);

                try {
                    $product = ServiceProduct::query()->with('service')->findOrFail((int) $item['product_id']);

                    if (!$product->service || !$product->enabled || !$product->service->enabled) {
                        throw new \RuntimeException('The selected VTU product or service is unavailable.');
                    }

                    $this->validator->validate($product->service, $payload);

                    $transaction = $this->transactions->process(
                        $this->transactions->create($uid, $product, $payload, $tier, $key),
                    );

                    $this->syncItemFromTransaction($row, $transaction);
                } catch (\Throwable $e) {
                    Log::warning('VTU bulk item processing failed.', [
                        'bulk_operation_id' => $bulk->id,
                        'bulk_item_id' => $row->id,
                        'user_id' => $uid,
                        'exception' => get_class($e),
                    ]);

                    $transaction = VtuTransaction::query()
                        ->where('idempotency_key', $key)
                        ->where('user_id', $uid)
                        ->first();

                    if ($transaction) {                        $this->syncItemFromTransaction($row, $transaction);
                    } else {
                        $row->update([
                            'status' => 'pending',
                            'error_message' => 'Bulk worker stopped before this transaction was created. The item is queued for safe resume using its idempotency key.',
                        ]);
                    }
                }
            }
        } finally {
            $this->releaseWorkerLease($bulk, $leaseToken);
        }

        return $this->recalculate($bulk->fresh('items'));
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
            if ($until && now()->lt(\Illuminate\Support\Carbon::parse($until))) {
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

    public function cancel(int $userId, VtuBulkOperation $bulk, string $reason = 'Cancelled by user'): VtuBulkOperation
    {
        return DB::transaction(function () use ($userId, $bulk, $reason): VtuBulkOperation {
            $locked = VtuBulkOperation::query()->lockForUpdate()->findOrFail($bulk->id);
            if ((int) $locked->user_id !== $userId) {
                throw new \RuntimeException('You cannot cancel this bulk operation.');
            }
            if (in_array($locked->status, ['successful','failed','partial','cancelled'], true)) {
                return $locked->load('items');
            }

            $items = $locked->items()->lockForUpdate()->get();
            foreach ($items as $item) {
                if (in_array($item->status, ['pending','processing'], true)) {
                    $item->update([
                        'status' => 'cancelled',
                        'error_message' => $reason,
                    ]);
                }
            }
            $metadata = array_merge((array) $locked->metadata, [
                'cancelled_at' => now()->toIso8601String(),
                'cancelled_by' => $userId,
                'cancellation_reason' => $reason,
            ]);
            $locked->metadata = $metadata;
            $locked->status = 'cancelled';
            $locked->save();

            return $locked->fresh('items');
        });
    }

    public function cancelAdmin(VtuBulkOperation $bulk, int $operatorId, string $reason): VtuBulkOperation
    {
        return DB::transaction(function () use ($bulk, $operatorId, $reason): VtuBulkOperation {
            $locked = VtuBulkOperation::query()->lockForUpdate()->findOrFail($bulk->id);
            if (in_array($locked->status, ['successful','failed','partial','cancelled'], true)) {
                return $locked->load('items');
            }

            $items = $locked->items()->lockForUpdate()->get();
            foreach ($items as $item) {
                if (in_array($item->status, ['pending','processing'], true)) {
                    $item->update([
                        'status' => 'cancelled',
                        'error_message' => $reason,
                    ]);
                }
            }

            $locked->metadata = array_merge((array) $locked->metadata, [
                'cancelled_at' => now()->toIso8601String(),
                'cancelled_by_admin' => $operatorId,
                'cancellation_reason' => $reason,
            ]);
            $locked->status = 'cancelled';
            $locked->save();

            return $locked->fresh('items');
        });
    }

    public function cancelAdminSelected(array $bulkIds, int $operatorId, string $reason): array
    {
        $cancelled = 0;
        $skipped = 0;
        foreach (VtuBulkOperation::query()->whereIn('id', $bulkIds)->orderBy('id')->get() as $bulk) {
            try {
                $result = DB::transaction(function () use ($bulk, $operatorId, $reason): array {
                    $locked = VtuBulkOperation::query()->lockForUpdate()->findOrFail($bulk->id);
                    if (in_array($locked->status, ['successful','failed','partial','cancelled'], true)) {
                        return ['cancelled'=>false,'skipped'=>true];
                    }
                    $items = $locked->items()->lockForUpdate()->with('transaction')->get();
                    $changed = 0;
                    foreach ($items as $item) {
                        if (!in_array($item->status, ['pending','processing','scheduled'], true) || !$item->transaction) continue;
                        try {
                            $tx = $this->transactions->cancelPending($item->transaction, $reason);
                            $item->update(['status'=>$tx->status,'amount_minor'=>$tx->total_minor,'error_message'=>$tx->failure_message]);
                            if ($tx->status === 'cancelled') $changed++;
                        } catch (\Throwable $e) {
                            Log::warning('VTU selected bulk cancellation skipped an unsafe item.', ['bulk_operation_id'=>$locked->id,'bulk_item_id'=>$item->id,'operator_id'=>$operatorId,'exception'=>get_class($e)]);
                        }
                    }
                    $locked->metadata=array_merge((array)$locked->metadata,['last_admin_cancellation_at'=>now()->toIso8601String(),'last_admin_cancellation_by'=>$operatorId,'last_admin_cancellation_reason'=>$reason]);
                    $locked->save();
                    $this->recalculate($locked->fresh('items'));
                    return ['cancelled'=>$changed>0,'skipped'=>$changed===0];
                });
                $result['cancelled'] ? $cancelled++ : $skipped++;
            } catch (\Throwable $e) {
                report($e);
                $skipped++;
            }
        }
        return ['cancelled'=>$cancelled,'skipped'=>$skipped];
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
                now()->lt(\Illuminate\Support\Carbon::parse($metadata['worker_lease_until']))) {
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
                'last_recovery_at' => now()->toIso8601String(),            ]);
            $bulk->metadata = $metadata;
            $bulk->save();
            $this->recalculate($bulk->fresh('items'));
        }

        return $recovered;
    }

    private function quoteFingerprint(array $items, string $tier, array $rows): string
    {
        $canonical = [
            'tier' => strtoupper($tier),
            'items' => array_values(array_map(
                static fn (array $item): array => [
                    'product_id' => (int) $item['product_id'],
                    'payload' => (array) ($item['payload'] ?? []),
                ],
                $items,
            )),
            'prices' => array_values(array_map(
                static fn (array $row): array => [
                    'index' => (int) $row['index'],
                    'product_id' => (int) $row['product_id'],
                    'customer_price' => (string) $row['customer_price'],
                    'currency' => (string) $row['currency'],
                ],
                $rows,
            )),
        ];

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
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

    public function requeryItem(int $uid, VtuBulkOperation $bulk, int $itemId): VtuBulkOperation
    {
        if ((int) $bulk->user_id !== $uid) {
            throw new RuntimeException('Bulk operation not found.');
        }

        $item = $bulk->items()->whereKey($itemId)->firstOrFail();
        $transaction = $item->transaction;

        if (!$transaction) {
            throw new \RuntimeException('This bulk item has no transaction to requery yet.');
        }

        if ($transaction->isTerminal()) {
            return $this->recalculate($bulk->fresh('items'));
        }

        // Requery is the only safe recovery path for an ambiguous provider state.
        // Never re-initiate a pending/unknown provider transaction from the bulk worker.
        $this->transactions->requery($transaction);

        return $this->recalculate($bulk->fresh('items'));
    }

    public function recalculate(VtuBulkOperation $bulk): VtuBulkOperation
    {
        return DB::transaction(function () use ($bulk): VtuBulkOperation {
            $locked = VtuBulkOperation::query()->lockForUpdate()->findOrFail($bulk->id);
            $successful = $locked->items()->where('status', 'successful')->count();
            $failed = $locked->items()->whereIn('status', ['failed', 'reversed', 'cancelled'])->count();
            $pending = max(0, $locked->total_items - $successful - $failed);

            $locked->successful_items = $successful;
            $locked->failed_items = $failed;
            $locked->processed_items = $successful + $failed;
            $locked->status = match (true) {
                $pending > 0 => 'pending',
                $failed > 0 && $successful > 0 => 'partial',
                $failed > 0 => 'failed',
                default => 'successful',
            };
            $locked->metadata = array_merge((array) $locked->metadata, [
                'pending_items' => $pending,
            ]);
            $locked->save();

            return $locked->fresh('items');
        });
    }

    private function assertFingerprintMatches(VtuBulkOperation $bulk, string $fingerprint, int $itemCount): void
    {
        $metadata = (array) $bulk->metadata;

        if (($metadata['request_fingerprint'] ?? null) !== $fingerprint ||
            (int) $bulk->total_items !== $itemCount) {            throw new \RuntimeException('Bulk idempotency key has already been used for a different request.');
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