<?php

namespace App\Console\Commands;

use App\Models\VtuBulkOperation;
use App\Services\Vtu\VtuBulkService;
use App\Services\Vtu\VtuTransactionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessScheduledVtuBulk extends Command
{
    protected $signature = 'vtu:process-scheduled-bulk {--limit=50}';
    protected $description = 'Execute VTU bulk operations whose scheduled time has arrived.';

    public function handle(VtuTransactionService $transactions, VtuBulkService $bulkService): int
    {
        $limit = max(1, min((int) $this->option('limit'), 100));
        $operations = VtuBulkOperation::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;

        foreach ($operations as $operation) {
            $claimed = DB::transaction(function () use ($operation): bool {
                $locked = VtuBulkOperation::query()->lockForUpdate()->find($operation->id);
                if (!$locked || $locked->status !== 'scheduled' || !$locked->scheduled_at || $locked->scheduled_at->isFuture()) {
                    return false;
                }

                $locked->status = 'processing';
                $locked->metadata = array_merge((array) $locked->metadata, [
                    'scheduled_worker_claimed_at' => now()->toIso8601String(),
                ]);
                $locked->save();

                return true;
            }, 3);

            if (!$claimed) {
                continue;
            }

            $bulk = $operation->fresh('items');
            foreach ($bulk->items()->whereIn('status', ['scheduled', 'pending'])->orderBy('sequence')->get() as $item) {
                $transaction = $item->transaction;
                if (!$transaction) {
                    $item->update([
                        'status' => 'failed',
                        'error_message' => 'Scheduled bulk item has no linked VTU transaction.',
                    ]);
                    continue;
                }

                try {
                    $result = $transactions->process($transaction);
                    $item->update([
                        'status' => $result->status,
                        'amount_minor' => $result->total_minor,
                        'error_message' => $result->failure_message,
                    ]);
                } catch (Throwable $exception) {
                    Log::warning('Scheduled VTU bulk item could not be processed; reconciliation is required.', [
                        'bulk_operation_id' => $bulk->id,
                        'bulk_item_id' => $item->id,
                        'transaction_id' => $transaction->id,
                        'exception' => get_class($exception),
                    ]);
                    $item->update([
                        'status' => 'processing',
                        'error_message' => 'Provider state may be uncertain; reconciliation is required before retry.',
                    ]);
                }
            }

            $bulkService->recalculate($bulk->fresh('items'));
            $processed++;
        }

        $this->info("Processed {$processed} scheduled VTU bulk operation(s).");

        return self::SUCCESS;
    }
}
