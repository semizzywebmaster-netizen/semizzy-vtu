<?php
namespace Addons\WhatsAppBot\Http\Controllers;

use Addons\CommunicationWhatsapp\Services\WhatsAppWebhookService;
use Addons\CommunicationWhatsapp\Services\CommunicationProviderGateway;
use App\Models\Communication\Conversation;
use App\Models\Communication\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppBotWebhookController
{
 public function receive(Request $request, WhatsAppWebhookService $webhook, CommunicationProviderGateway $gateway): Response {
  $provider=$this->provider($request);
  if(!$provider) return response('WhatsApp bot provider unavailable',503);
  try { $webhook->handle($provider,$request->getContent(),$request->header('X-Hub-Signature-256')); } catch(\Throwable $e) { return response('Webhook rejected',400); }
  $payload=json_decode($request->getContent(),true) ?: [];
  $from=$this->from($payload);
  if(!$from) return response('OK',200);
  $user=User::query()->where('phone',$from)->first();
  $body=strtolower(trim((string)$this->body($payload)));
  $conversation=Conversation::firstOrCreate(['channel'=>'whatsapp','external_contact'=>$from],['user_id'=>$user?->id,'status'=>'open']);
  if(!$user || !$user->whatsapp_verified_at || !$user->whatsapp_transaction_enabled){
   $reply='This WhatsApp number is not authorized for transactions. Register this number on SEMIZZY ONE, log in, then complete WhatsApp verification before transacting.';
  } elseif(in_array($body,['hi','hello','menu','start'],true)){
   $reply='Welcome to SEMIZZY ONE. Reply with a service command such as AIRTIME 500 08012345678, or MENU to see available services.';
  } else {
   $reply='Your WhatsApp account is verified. Transaction commands are routed through the platform transaction engine; invalid or unsupported commands will not debit your wallet.';
  }
  $message=Message::create(['conversation_id'=>$conversation->id,'user_id'=>$user?->id,'channel'=>'whatsapp','direction'=>'outbound','recipient'=>$from,'body'=>$reply,'status'=>'queued','idempotency_key'=>'wa-bot:'.sha1($from.'|'.$body.'|'.now()->format('YmdHi')),'metadata'=>['bot'=>true]]);
  try{$gateway->send($message);}catch(\Throwable $e){}
  return response('OK',200);
 }
 private function provider(?Request $request){return \App\Models\Communication\Provider::query()->where('channel','whatsapp')->where('enabled',true)->where('paused',false)->orderBy('priority')->first();}
 private function from(array $p): ?string { foreach(($p['entry']??[]) as $e) foreach(($e['changes']??[]) as $c) foreach(($c['value']['messages']??[]) as $m) if(!empty($m['from'])) return (string)$m['from']; return $p['from']??$p['sender']??null; }
 private function body(array $p): ?string { foreach(($p['entry']??[]) as $e) foreach(($e['changes']??[]) as $c) foreach(($c['value']['messages']??[]) as $m) return $m['text']['body']??$m['button']['text']??null; return $p['message']??null; }
}
