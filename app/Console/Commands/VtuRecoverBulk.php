<?php

namespace App\Console\Commands;

use App\Services\Vtu\VtuBulkService;
use Illuminate\Console\Command;

class VtuRecoverBulk extends Command
{
    protected $signature = 'vtu:recover-bulk {--limit=50} {--stale-minutes=10}';
    protected $description = 'Recover stale VTU bulk operations after interrupted workers.';

    public function handle(VtuBulkService $bulk): int
    {
        $count = $bulk->recoverStaleOperations(
            (int) $this->option('limit'),
            (int) $this->option('stale-minutes'),
        );

        $this->info("Recovered or reconciled {$count} bulk item(s).");

        return self::SUCCESS;
    }
}
