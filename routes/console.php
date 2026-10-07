<?php
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Artisan;
use Semizzy\Addons\SimHosting\Services\SimHostingService;
use Semizzy\Addons\Exams\Services\ExamResultService;

Schedule::command('bulk-sms:dispatch',['--limit'=>100])->everyMinute()->withoutOverlapping(2)->onOneServer();
Schedule::command('bulk-sms:reconcile',['--limit'=>100])->everyFiveMinutes()->withoutOverlapping(5)->onOneServer();
Artisan::command('bulk-sms:dispatch {--limit=100}',function(\Semizzy\Addons\BulkSms\Services\BulkSmsService $s){$this->info('Dispatched '.$s->dispatch((int)$this->option('limit')).' SMS messages.');});
Artisan::command('bulk-sms:reconcile {--limit=100}',function(\Semizzy\Addons\BulkSms\Services\BulkSmsService $s){$this->info('Reconciled '.$s->reconcile((int)$this->option('limit')).' SMS messages.');});
Schedule::command('queue:work database',['--stop-when-empty'=>true,'--max-time'=>50,'--tries'=>3])->everyMinute()->withoutOverlapping(2)->onOneServer();
Schedule::command('queue:prune-failed',['--hours'=>168])->weekly();
Schedule::command('vtu:reconcile-pending',['--limit'=>50])->everyFiveMinutes()->withoutOverlapping(5)->onOneServer();
Schedule::command('vtu:recover-bulk',['--limit'=>50,'--stale-minutes'=>10])->everyFiveMinutes()->withoutOverlapping(5)->onOneServer();
Schedule::command('vtu:recover-stale-initiations',['--limit'=>50,'--stale-minutes'=>10])->everyFiveMinutes()->withoutOverlapping(5)->onOneServer();
Schedule::command('cac:reconcile-pending',['--limit'=>50,'--min-age'=>1])->everyFiveMinutes()->withoutOverlapping(5)->onOneServer();
Schedule::command('payments:expire-pending',['--limit'=>100])->everyMinute()->withoutOverlapping(2)->onOneServer();
Schedule::command('savings:process-maturity',['--limit'=>100])->everyMinute()->withoutOverlapping(2)->onOneServer();
Schedule::command('loans:process-overdue',['--limit'=>100])->everyMinute()->withoutOverlapping(2)->onOneServer();
Schedule::command('investments:process-maturity',['--limit'=>100])->everyMinute()->withoutOverlapping(2)->onOneServer();
Schedule::command('sim-hosting:expire',['--limit'=>100])->everyMinute()->withoutOverlapping(2)->onOneServer();
Schedule::command('sim-hosting:reconcile',['--limit'=>100])->everyFiveMinutes()->withoutOverlapping(5)->onOneServer();
Schedule::command('exams:reconcile')->everyFiveMinutes()->withoutOverlapping(5)->onOneServer();
Artisan::command('sim-hosting:expire {--limit=100}',function(SimHostingService $service){$this->info('Expired '.$service->expire((int)$this->option('limit')).' rentals.');});
Artisan::command('sim-hosting:reconcile {--limit=100}',function(SimHostingService $service){$this->info('Reconciled '.$service->reconcile((int)$this->option('limit')).' rentals.');});
Artisan::command('exams:reconcile',function(ExamResultService $service){$this->info('Reconciled '.$service->reconcile().' exam transactions.');});

Schedule::call(function (): void {
    if (!\App\Models\Addon::query()->where('identifier', 'escrow.protection')->where('status', 'active')->exists()) {
        return;
    }

    foreach (\Semizzy\Addons\Escrow\Models\EscrowTransaction::query()
        ->where('status', 'funded')
        ->whereNotNull('expires_at')
        ->where('expires_at', '<=', now())
        ->pluck('id') as $id) {
        app(\Semizzy\Addons\Escrow\Services\EscrowService::class)->expire((int) $id);
    }
})->everyFiveMinutes()->name('escrow-expiry-reconciliation')->withoutOverlapping()->onOneServer();

Schedule::command('communication:campaigns')->everyMinute()->withoutOverlapping(2)->onOneServer();
Artisan::command('communication:campaigns',function(\Addons\CommunicationWhatsapp\Services\CommunicationCampaignService $service){$count=0; \App\Models\Communication\Campaign::query()->whereIn('status',['draft','scheduled','running'])->where(fn($q)=>$q->whereNull('scheduled_at')->orWhere('scheduled_at','<=',now()))->orderBy('id')->limit(50)->get()->each(function($campaign)use($service,&$count){$service->process($campaign,500);$count++;}); $this->info("Processed {$count} communication campaigns.");});
