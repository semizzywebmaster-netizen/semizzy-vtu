<?php
namespace Addons\CommunicationWhatsapp\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Communication\Campaign;
use Addons\CommunicationWhatsapp\Services\CommunicationCampaignService;

class RunCommunicationCampaigns extends Command
{
 protected $signature='communication:campaigns {--limit=500}';
 protected $description='Process due communication campaigns using database scheduling.';
 public function handle(CommunicationCampaignService $service): int
 {
  $campaigns=Campaign::whereIn('status',['draft','scheduled'])
   ->where(fn($q)=>$q->whereNull('scheduled_at')->orWhere('scheduled_at','<=',now()))
   ->orderBy('id')->limit(50)->get();
  foreach($campaigns as $campaign){
   try{$this->line($campaign->id.': '.json_encode($service->process($campaign,max(1,(int)$this->option('limit')))));}
   catch(\Throwable $e){$campaign->update(['status'=>'failed']);$this->error($campaign->id.': '.$e->getMessage());}
  }
  return self::SUCCESS;
 }
}