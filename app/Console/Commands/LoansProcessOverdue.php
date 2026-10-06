<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Semizzy\Addons\Loans\Services\LoanService;
class LoansProcessOverdue extends Command {protected $signature='loans:process-overdue {--limit=100}';protected $description='Mark overdue loans.';public function handle(LoanService $s):int{$n=$s->markOverdue((int)$this->option('limit'));$this->info("Marked {$n} loan(s) overdue.");return self::SUCCESS;}}