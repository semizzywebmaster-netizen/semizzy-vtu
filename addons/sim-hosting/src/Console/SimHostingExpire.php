<?php

namespace Semizzy\Addons\SimHosting\Console;

use Illuminate\Console\Command;
use Semizzy\Addons\SimHosting\Services\SimHostingService;

class SimHostingExpire extends Command
{
    protected $signature='sim-hosting:expire {--limit=100}';
    protected $description='Expire SIM Hosting rentals whose rental period has ended.';
    public function handle(SimHostingService $service): int { $this->info('Expired '.$service->expire((int)$this->option('limit')).' rentals.'); return self::SUCCESS; }
}