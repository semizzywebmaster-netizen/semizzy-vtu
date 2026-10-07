<?php
namespace Addons\CommunicationWhatsapp\Services;
use App\Models\Communication\DeliveryAttempt;
use App\Models\Communication\Message;
use App\Models\Communication\Provider;
use Illuminate\Support\Facades\DB;
class WhatsAppStatusService {
 public function handle(Provider $provider,array $payload): int {
  $updates=0;
  foreach($this->extract($payload) as $status){
   $external=$status['external_id'] ?? null; if(!$external) continue;
   $message=Message::query()->where('channel','whatsapp')->where('external_message_id',$external)->first(); if(!$message) continue;
   $attempt=DeliveryAttempt::query()->where('message_id',$message->id)->where('external_message_id',$external)->latest('id')->first();
   DB::transaction(function()use($message,$attempt,$status){
    $state=$status['status']; $data=['status'=>$state];
    if($state==='delivered') $data['delivered_at']=now();
    if($state==='failed') $data['failed_at']=now();
    $message->update($data);
    if($attempt) $attempt->update(['status'=>$state,'response'=>isset($status['error'])?json_encode($status['error']):$attempt->response,'completed_at'=>in_array($state,['delivered','failed'],true)?now():$attempt->completed_at]);
   }); $updates++;
  } return $updates;
 }
 private function extract(array $payload): array {
  $out=[];
  foreach(($payload['entry'] ?? []) as $entry) foreach(($entry['changes'] ?? []) as $change) foreach((($change['value'] ?? [])['statuses'] ?? []) as $status)
   $out[]=['external_id'=>$status['id'] ?? null,'status'=>match($status['status'] ?? null){'sent'=>'sent','delivered'=>'delivered','read'=>'delivered','failed'=>'failed',default=>'pending'},'error'=>$status['errors'] ?? null];
  return $out;
 }
}