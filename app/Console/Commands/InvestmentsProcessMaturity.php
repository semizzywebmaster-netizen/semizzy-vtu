<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Semizzy\Addons\Investments\Services\InvestmentService;
class InvestmentsProcessMaturity extends Command { protected $signature='investments:process-maturity {--limit=100}'; protected $description='Process matured investments and profit.'; public function handle(InvestmentService $s):int{$n=$s->processMaturity((int)$this->option('limit'));$this->info("Processed {$n} investments.");return self::SUCCESS;} }