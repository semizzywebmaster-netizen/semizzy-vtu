<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Semizzy\Addons\Savings\Services\SavingsMaturityService;

class SavingsProcessMaturity extends Command
{
    protected $signature = 'savings:process-maturity {--limit=100}';
    protected $description = 'Process matured savings accounts.';

    public function handle(SavingsMaturityService $service): int
    {
        $this->info('Processed '.$service->process((int) $this->option('limit')).' savings accounts.');
        return self::SUCCESS;
    }
}
