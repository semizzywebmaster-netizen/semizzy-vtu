<?php

namespace App\Console\Commands;

use App\Models\VtuTransaction;
use App\Services\Vtu\VtuTransactionService;
use Illuminate\Console\Command;

class VtuRecoverStaleInitiations extends Command
{
    protected $signature = 'vtu:recover-stale-initiations {--limit=50} {--stale-minutes=10}';

    protected $description = 'Safely recover VTU transactions whose provider initiation claim became stale.';

    public function handle(VtuTransactionService $service): int
    {
        $limit = max(1, min((int) $this->option('limit'), 200));
        $staleMinutes = max(1, (int) $this->option('stale-minutes'));

        $items = VtuTransaction::query()
            ->whereIn('status', ['processing', 'pending'])
            ->oldest('id')
            ->limit($limit)
            ->get();

        $recovered = 0;

        foreach ($items as $tx) {
            $metadata = (array) $tx->metadata;
            if (($metadata['provider_initiation_claimed'] ?? false) !== true) {
                continue;
            }

            if (empty($metadata['provider_initiation_claimed_at'])) {
                continue;
            }

            try {
                $before = $tx->fresh();
                $after = $service->recoverStaleInitiationClaim($before, $staleMinutes);

                if (($after->metadata['provider_initiation_recovery_at'] ?? null) !== ($before->metadata['provider_initiation_recovery_at'] ?? null)) {
                    $recovered++;
                    $this->line($after->reference . ' recovered safely.');
                }
            } catch (\Throwable $e) {
                $this->warn($tx->reference . ' left unchanged: ' . $e->getMessage());
            }
        }

        $this->info("Recovered {$recovered} stale initiation claim(s).");

        return self::SUCCESS;
    }
}
