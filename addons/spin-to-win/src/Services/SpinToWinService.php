<?php
namespace Semizzy\Addons\SpinToWin\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Semizzy\Addons\SpinToWin\Models\SpinCampaign;
use Semizzy\Addons\SpinToWin\Models\SpinPrize;
use Semizzy\Addons\SpinToWin\Models\SpinPlay;
class SpinToWinService {
 public function play(int $userId,int $campaignId,string $operationKey):SpinPlay {
  return DB::transaction(function()use($userId,$campaignId,$operationKey){
   $existing=SpinPlay::query()->where('operation_key',$operationKey)->lockForUpdate()->first();
   if($existing)return $existing->load('prize');
   $campaign=SpinCampaign::query()->where('status','active')->lockForUpdate()->findOrFail($campaignId);
   $now=now();
   if($campaign->starts_at&&$now->lt($campaign->starts_at))throw new RuntimeException('This campaign has not started.');
   if($campaign->ends_at&&$now->gt($campaign->ends_at))throw new RuntimeException('This campaign has ended.');
   if($campaign->total_play_limit!==null&&$campaign->plays()->count()>=$campaign->total_play_limit)throw new RuntimeException('Campaign play limit reached.');
   $daily=$campaign->plays()->where('user_id',$userId)->where('played_at','>=',$now->copy()->startOfDay())->count();
   if($daily>=$campaign->daily_play_limit)throw new RuntimeException('Your daily spin limit has been reached.');
   $prizes=$campaign->prizes()->where('active',true)->where(function($q){$q->whereNull('max_wins')->orWhereColumn('wins_count','<','max_wins');})->where('weight','>',0)->get();
   $total=(float)$prizes->sum(fn($p)=>(float)$p->weight);
   $roll=$total>0?random_int(0,1000000)/1000000*$total:0;
   $cursor=0;$selected=null;
   foreach($prizes as $prize){$cursor+=(float)$prize->weight;if($roll<=$cursor){$selected=$prize;break;}}
   $play=SpinPlay::create(['campaign_id'=>$campaign->id,'user_id'=>$userId,'prize_id'=>$selected?->id,'operation_key'=>$operationKey,'reward_event_key'=>$selected&&$selected->prize_type==='wallet'?'spin:'.$operationKey:null,'status'=>$selected?'won':'no_win','played_at'=>$now,'metadata'=>['randomized_server_side'=>true]]);
   if($selected)$selected->increment('wins_count');
   return $play->load('prize');
  });
 }
 public function issueMonetaryReward(SpinPlay $play,int $adminId):void {
  if(!$play->prize||$play->prize->prize_type!=='wallet')return;
  $eventKey=$play->reward_event_key ?: 'spin:'.$play->operation_key;
  $service=app(\Semizzy\Addons\Rewards\Services\RewardsService::class);
  $service->issueReward($play->user_id,'spin_to_win',$eventKey,null,['spin_play_id'=>$play->id,'prize_id'=>$play->prize_id]);
  $play->update(['status'=>'reward_pending_approval']);
 }
}