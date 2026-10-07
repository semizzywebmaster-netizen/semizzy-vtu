<?php
namespace Addons\CommunicationWhatsapp\Services;

use App\Models\Communication\Campaign;
use App\Models\Communication\Consent;
use App\Models\Communication\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CommunicationCampaignService
{
 public function create(array $data, ?User $creator=null): Campaign
 {
  $channel=$data['channel'];
  if(!in_array($channel,['whatsapp','sms','email','push'],true)) throw new RuntimeException('Unsupported campaign channel.');
  $campaign=Campaign::create([
   'created_by'=>$creator?->id,'name'=>$data['name'],'channel'=>$channel,'template_id'=>$data['template_id']??null,
   'audience'=>$data['audience']??[],'content'=>$data['content']??null,
   'status'=>!empty($data['scheduled_at'])?'scheduled':'draft','scheduled_at'=>$data['scheduled_at']??null
  ]);
  return $campaign->fresh('template');
 }

 public function process(Campaign $campaign, int $limit=500): array
 {
  if(!in_array($campaign->status,['draft','scheduled','running'],true)) return ['processed'=>0,'sent'=>0,'failed'=>0,'skipped'=>0];
  if($campaign->scheduled_at && $campaign->scheduled_at->isFuture()) return ['processed'=>0,'sent'=>0,'failed'=>0,'skipped'=>0];
  $campaign->loadMissing('template');
  if(!$campaign->template && !$campaign->content) throw new RuntimeException('Campaign needs a template or content.');
  $campaign->update(['status'=>'running','started_at'=>$campaign->started_at ?: now()]);

  $sent=$failed=$skipped=$processed=0;
  $users=$this->audience($campaign->audience ?: [])->whereNotExists(function($q)use($campaign){$q->selectRaw('1')->from('communication_messages as cm')->whereColumn('cm.user_id','users.id')->where('cm.campaign_id',$campaign->id);});
  $users=$users->limit(max(1,$limit))->get();
  $renderer=app(CommunicationTemplateService::class);
  $gateway=app(CommunicationProviderGateway::class);

  foreach($users as $user){
   $processed++;
   $recipient=$this->recipient($user,$campaign->channel);
   if(!$recipient){$skipped++;continue;}
   if(!$this->consented($user,$campaign->channel)){ $skipped++; continue; }
   try{
    $rendered=$campaign->template ? $renderer->render($campaign->template,$this->variables($user)) : ['subject'=>null,'body'=>$campaign->content];
    $key='campaign:'.$campaign->id.':user:'.$user->id;
    $message=Message::firstOrCreate(
      ['channel'=>$campaign->channel,'idempotency_key'=>$key],
      ['campaign_id'=>$campaign->id,'user_id'=>$user->id,'channel'=>$campaign->channel,'direction'=>'outbound','recipient'=>$recipient,'body'=>$rendered['body'],'metadata'=>['subject'=>$rendered['subject']],'status'=>'queued']
    );
    if($message->status==='sent' || $message->status==='delivered'){ $sent++; continue; }
    $gateway->send($message); $sent++;
   }catch(\Throwable $e){$failed++;}
  }
  if($users->isEmpty()) $campaign->update(['status'=>'completed','completed_at'=>now()]);
  return compact('processed','sent','failed','skipped');
 }

 protected function audience(array $a)
 {
  $q=User::query()->where('status','active');
  if(!empty($a['user_ids'])) $q->whereIn('id',array_map('intval',(array)$a['user_ids']));
  if(!empty($a['tier'])) $q->whereIn('tier',array_map('intval',(array)$a['tier']));
  if(!empty($a['role'])) $q->whereIn('role',(array)$a['role']);
  if(!empty($a['account_type'])) $q->whereIn('account_type',(array)$a['account_type']);
  if(!empty($a['country'])) $q->whereIn('country',(array)$a['country']);
  if(!empty($a['state'])) $q->whereIn('state',(array)$a['state']);
  if(!empty($a['segment']) && is_array($a['segment'])){
   foreach($a['segment'] as $field=>$value){
    if(in_array($field,['tier','role','account_type','country','state','status'],true)) $q->whereIn($field,(array)$value);
   }
  }
  return $q;
 }

 protected function recipient(User $u,string $channel): ?string
 {
  return match($channel){ 'email'=>$u->email, 'whatsapp','sms'=>$u->phone, default=>null };
 }

 protected function consented(User $u,string $channel): bool
 {
  return (bool) Consent::query()->where('user_id',$u->id)->where('channel',$channel)->where('purpose','marketing')->where('opted_in',true)->exists();
 }

 protected function variables(User $u): array
 {
  return ['id'=>$u->id,'name'=>$u->name,'username'=>$u->username,'email'=>$u->email,'phone'=>$u->phone,'tier'=>$u->tier,'role'=>$u->role];
 }
}
