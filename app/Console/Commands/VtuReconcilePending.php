<?php
namespace App\Console\Commands;
use App\Models\VtuTransaction;
use App\Services\Vtu\VtuTransactionService;
use Illuminate\Console\Command;
class VtuReconcilePending extends Command{
 protected $signature='vtu:reconcile-pending {--limit=50}';protected $description='Safely requery pending VTU transactions with provider references.';
 public function handle(VtuTransactionService $service):int{
  $items=VtuTransaction::query()->where('status','pending')->whereNotNull('provider_reference')->oldest('id')->limit((int)$this->option('limit'))->get();
  foreach($items as $tx){try{$service->requery($tx);$this->line($tx->reference.' reconciled.');}catch(\Throwable $e){$this->warn($tx->reference.' left pending: '.$e->getMessage());}}
  return self::SUCCESS;
 }
}