<?php
namespace App\Console\Commands;

use App\Models\VtuTransaction;
use App\Services\Vtu\VtuTransactionService;
use Illuminate\Console\Command;

class VtuReconcilePending extends Command
{
    protected $signature = 'vtu:reconcile-pending {--limit=50} {--min-age=1}';
    protected $description = 'Safely requery pending VTU transactions with provider references.';

    public function handle(VtuTransactionService $service): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $minAge = max(0, min(60, (int) $this->option('min-age')));

        $items = VtuTransaction::query()
            ->where('status', 'pending')
            ->whereNotNull('provider_reference')
            ->where('updated_at', '<=', now()->subMinutes($minAge))
            ->oldest('updated_at')
            ->oldest('id')
            ->limit($limit)
            ->get();

        $successful = 0;
        $failed = 0;
        $stillPending = 0;

        foreach ($items as $tx) {
            try {
                $updated = $service->requery($tx);

                if ($updated->status === 'successful') {
                    $successful++;
                    $this->info($updated->reference.' resolved: successful.');
                } elseif ($updated->status === 'failed') {
                    $failed++;
                    $this->warn($updated->reference.' resolved: failed.');
                } else {
                    $stillPending++;
                    $this->line($updated->reference.' remains pending; no retry initiated.');
                }
            } catch (\Throwable $e) {
                $stillPending++;
                $this->warn($tx->reference.' remains pending: reconciliation attempt failed safely.');
                report($e);
            }
        }

        $this->line(sprintf(
            'Reconciliation complete: %d checked, %d successful, %d failed, %d still pending.',
            $items->count(),
            $successful,
            $failed,
            $stillPending
        ));

        return self::SUCCESS;
    }
}
