<?php
namespace Addons\CommunicationWhatsapp\Services;

use App\Models\Communication\Conversation;
use App\Models\Communication\Message;
use App\Models\Communication\Provider;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WhatsAppWebhookService
{
 public function verify(Provider $provider, ?string $mode, ?string $token, ?string $challenge): string
 {
  $secret=$provider->credentials['verify_token'] ?? null;
  if($mode !== 'subscribe' || !$secret || !hash_equals((string)$secret,(string)$token)) throw new RuntimeException('Webhook verification failed.');
  return (string)$challenge;
 }

 public function handle(Provider $provider,string $rawBody,?string $signature): int
 {
  $credentials=$provider->credentials ?: [];
  $secret=$credentials['webhook_secret'] ?? null;
  if($secret){
   $expected='sha256='.hash_hmac('sha256',$rawBody,$secret);
   if(!$signature || !hash_equals($expected,$signature)) throw new RuntimeException('Invalid webhook signature.');
  }
  $payload=json_decode($rawBody,true);
  if(!is_array($payload)) throw new RuntimeException('Malformed webhook payload.');

  $events=$this->extract($payload);
  $stored=0;
  foreach($events as $event){
   if(empty($event['external_id']) || empty($event['from'])) continue;
   $idempotency='whatsapp:'.$event['external_id'];
   if(Message::query()->where('channel','whatsapp')->where('idempotency_key',$idempotency)->exists()) continue;

   $user=User::query()->where('phone',$event['from'])->first();
   $conversation=Conversation::query()
    ->where('channel','whatsapp')->where('external_contact',$event['from'])
    ->when($event['thread_id'] ?? null,fn($q,$v)=>$q->where('external_thread_id',$v))
    ->first();

   $message=DB::transaction(function() use($user,$conversation,$event,$idempotency){
    $conversation ??= Conversation::create([
     'user_id'=>$user?->id,'channel'=>'whatsapp','external_contact'=>$event['from'],
     'external_thread_id'=>$event['thread_id'] ?? null,'status'=>'open','last_message_at'=>now()
    ]);
    $conversation->update(['user_id'=>$conversation->user_id ?: $user?->id,'status'=>'open','last_message_at'=>now()]);
    return Message::create([
     'conversation_id'=>$conversation->id,'user_id'=>$user?->id,'channel'=>'whatsapp',
     'direction'=>'inbound','recipient'=>$event['from'],'external_message_id'=>$event['external_id'],
     'body'=>$event['body'],'metadata'=>$event['metadata'] ?? [],'status'=>'received',
     'idempotency_key'=>$idempotency,'sent_at'=>null
    ]);
   });
   if($message) $stored++;
  }
  return $stored;
 }

 private function extract(array $payload): array
 {
  $events=[];
  foreach(($payload['entry'] ?? []) as $entry){
   foreach(($entry['changes'] ?? []) as $change){
    $value=$change['value'] ?? [];
    foreach(($value['messages'] ?? []) as $message){
     $type=$message['type'] ?? 'unknown';
     $body=$message['text']['body'] ?? $message['button']['text'] ?? $message['interactive']['button_reply']['title'] ?? $message['interactive']['list_reply']['title'] ?? '['.$type.']';
     $events[]=[
      'external_id'=>$message['id'] ?? null,
      'from'=>$message['from'] ?? null,
      'thread_id'=>$value['metadata']['phone_number_id'] ?? null,
      'body'=>$body,
      'metadata'=>['type'=>$type,'raw'=>$message]
     ];
    }
   }
  }
  if(!$events && isset($payload['message'])){
   $events[]=[
    'external_id'=>$payload['id'] ?? $payload['message_id'] ?? null,
    'from'=>$payload['from'] ?? $payload['sender'] ?? null,
    'thread_id'=>$payload['thread_id'] ?? null,
    'body'=>(string)$payload['message'],
    'metadata'=>['raw'=>$payload]
   ];
  }
  return $events;
 }
}
