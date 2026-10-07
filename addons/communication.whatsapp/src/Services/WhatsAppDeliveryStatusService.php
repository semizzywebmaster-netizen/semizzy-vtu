<?php
namespace Addons\CommunicationWhatsapp\Services;

use App\Models\Communication\DeliveryAttempt;
use App\Models\Communication\Message;
use Illuminate\Support\Facades\DB;

class WhatsAppDeliveryStatusService
{
 public function handle(array $payload): int
 {
  $updated=0;
  foreach (($payload['entry'] ?? []) as $entry) {
   foreach (($entry['changes'] ?? []) as $change) {
    foreach ((($change['value'] ?? [])['statuses'] ?? []) as $status) {
     $externalId=$status['id'] ?? null;
     $state=strtolower((string)($status['status'] ?? ''));
     if (!$externalId || !in_array($state,['sent','delivered','read','failed'],true)) continue;
     $message=Message::where('channel','whatsapp')->where('external_message_id',$externalId)->latest('id')->first();
     if (!$message) continue;
     DB::transaction(function() use ($message,$externalId,$state,$status) {
      $attempt=DeliveryAttempt::where('message_id',$message->id)->where('external_message_id',$externalId)->latest('id')->lockForUpdate()->first();
      $response=json_encode($status,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
      if ($attempt) {
       $attempt->update(['status'=>$state,'response'=>substr((string)$response,0,10000),'error'=>$state==='failed'?substr((string)($status['errors'][0]['title'] ?? $status['errors'][0]['message'] ?? 'Provider delivery failed'),0,2000):$attempt->error,'completed_at'=>in_array($state,['delivered','read','failed'],true)?now():$attempt->completed_at]);
      }
      $data=['status'=>$state];
      if (in_array($state,['delivered','read'],true)) $data['delivered_at']=$message->delivered_at ?: now();
      if ($state==='failed') $data['failed_at']=$message->failed_at ?: now();
      $message->update($data);
     });
     $updated++;
    }
   }
  }
  return $updated;
 }
}
