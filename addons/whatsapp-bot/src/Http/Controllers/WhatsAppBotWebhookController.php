<?php
namespace Addons\WhatsAppBot\Http\Controllers;

use Addons\CommunicationWhatsapp\Services\WhatsAppWebhookService;
use Addons\CommunicationWhatsapp\Services\CommunicationProviderGateway;
use App\Models\Communication\Conversation;
use App\Models\Communication\Message;
use App\Models\User;
use App\Models\Addon;
use App\Models\ServiceProduct;
use App\Services\Vtu\VtuTransactionService;
use App\Services\Vtu\VtuPayloadValidator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppBotWebhookController
{
 public function receive(Request $request, WhatsAppWebhookService $webhook, CommunicationProviderGateway $gateway, VtuTransactionService $transactions, VtuPayloadValidator $validator): Response {
  if(!Addon::query()->where('identifier','whatsapp.bot')->where('status','active')->exists()) return response('WhatsApp bot is currently disabled',503);
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
   $products=ServiceProduct::query()->with('service')->where('enabled',true)->whereHas('service',fn($q)=>$q->where('enabled',true))->orderBy('id')->limit(12)->get();
   $lines=$products->map(fn($p)=>'#'.$p->id.' '.$p->service->name.' - '.$p->name)->implode("\n");
   $reply="SEMIZZY ONE WhatsApp Services:\n".$lines."\n\nPurchase format:\nBUY product_id recipient amount PIN 1234\nExample: BUY 12 08012345678 500 PIN 1234";
  } elseif(str_starts_with($body,'buy ')){
   $parts=preg_split('/\\s+/',trim($body));
   if(count($parts)<5 || strtoupper($parts[count($parts)-2])!=='PIN' || !preg_match('/^\\d{4}$/',$parts[count($parts)-1])){
    $reply='Invalid purchase format. Use: BUY product_id recipient amount PIN 1234';
   } elseif(!Hash::check($parts[count($parts)-1],(string)$user->transaction_pin_hash)){
    $reply='Transaction PIN is invalid. Set or update your 4-digit transaction PIN on the website before using WhatsApp transactions.';
   } else {
    try {
     $productId=(int)$parts[1]; $recipient=$parts[2]; $amount=(float)$parts[3];
     $product=ServiceProduct::query()->with('service')->findOrFail($productId);
     abort_unless($product->enabled && $product->service?->enabled,422,'The selected service is currently unavailable.');
     $payload=['phone'=>$recipient,'recipient'=>$recipient,'amount'=>$amount];
     $validator->validate($product->service,$payload);
     $tx=$transactions->process($transactions->create($user->id,$product,$payload,$user->role,'wa:'.Str::uuid()));
     $reply="Transaction ".$tx->reference." has been submitted. Status: ".strtoupper($tx->status).". You can continue receiving updates on WhatsApp.";
    } catch(\Throwable $e) {
     $reply='Transaction was not submitted: '.substr($e->getMessage(),0,180);
    }
   }
  } else {
   $reply='I could not understand that command. Reply MENU to see services or use BUY product_id recipient amount PIN 1234.'; 
  }
  $message=Message::create(['conversation_id'=>$conversation->id,'user_id'=>$user?->id,'channel'=>'whatsapp','direction'=>'outbound','recipient'=>$from,'body'=>$reply,'status'=>'queued','idempotency_key'=>'wa-bot:'.sha1($from.'|'.$body.'|'.now()->format('YmdHi')),'metadata'=>['bot'=>true]]);
  try{$gateway->send($message);}catch(\Throwable $e){}
  return response('OK',200);
 }
 private function provider(?Request $request){return \App\Models\Communication\Provider::query()->where('channel','whatsapp')->where('enabled',true)->where('paused',false)->orderBy('priority')->first();}
 private function from(array $p): ?string { foreach(($p['entry']??[]) as $e) foreach(($e['changes']??[]) as $c) foreach(($c['value']['messages']??[]) as $m) if(!empty($m['from'])) return $this->canonicalPhone((string)$m['from']); return $this->canonicalPhone($p['from']??$p['sender']??null); }
 private function canonicalPhone(?string $phone): ?string { $p=preg_replace('/[^0-9+]/','',(string)$phone); if($p==='')return null; if(str_starts_with($p,'234'))return '+'.$p; if(str_starts_with($p,'0'))return '+234'.substr($p,1); return str_starts_with($p,'+')?$p:null; }
 private function body(array $p): ?string { foreach(($p['entry']??[]) as $e) foreach(($e['changes']??[]) as $c) foreach(($c['value']['messages']??[]) as $m) return $m['text']['body']??$m['button']['text']??null; return $p['message']??null; }
}
