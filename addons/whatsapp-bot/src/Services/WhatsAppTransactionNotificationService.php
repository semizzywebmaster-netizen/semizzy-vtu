<?php
namespace Addons\WhatsAppBot\Services;

use Addons\CommunicationWhatsapp\Services\CommunicationProviderGateway;
use App\Models\Addon;
use App\Models\Communication\Conversation;
use App\Models\Communication\Message;
use App\Models\VtuTransaction;
use Illuminate\Support\Str;

class WhatsAppTransactionNotificationService
{
 public function __construct(private CommunicationProviderGateway $gateway){}

 public function notify(VtuTransaction $tx, ?string $event=null): void
 {
  $tx->loadMissing('user','service','product');
  $user=$tx->user;
  if(!$user || !$user->whatsapp_verified_at || !$user->whatsapp_transaction_enabled)return;
  if(!Addon::query()->where('identifier','whatsapp.bot')->where('status','active')->exists())return;

  $status=strtolower((string)$tx->status);
  $event=$event ?: $status;
  $key='wa-tx:'.$tx->uuid.':'.$event;
  if(Message::query()->where('channel','whatsapp')->where('idempotency_key',$key)->exists())return;

  $recipient=$this->canonicalPhone((string)$user->phone);
  if(!$recipient)return;
  $conversation=Conversation::query()->where('channel','whatsapp')->where('user_id',$user->id)->where('external_contact',$recipient)->first();
  if(!$conversation){
   $conversation=Conversation::create([
    'user_id'=>$user->id,'channel'=>'whatsapp','external_contact'=>$recipient,
    'status'=>'open','last_message_at'=>now()
   ]);
  }

  $message=Message::create([
   'conversation_id'=>$conversation->id,'user_id'=>$user->id,'channel'=>'whatsapp',
   'direction'=>'outbound','recipient'=>$recipient,'body'=>$this->body($tx,$status),
   'status'=>'queued','idempotency_key'=>$key,
   'metadata'=>['bot'=>true,'transaction_id'=>$tx->id,'transaction_uuid'=>$tx->uuid,'event'=>$event]
  ]);
  try {
   $this->gateway->send($message);
   $conversation->update(['status'=>'open','last_message_at'=>now()]);
  } catch(\Throwable $e) {
   // Notification failure must never change or roll back the financial transaction.
  }
 }

 private function body(VtuTransaction $tx,string $status): string
 {
  $service=(string)($tx->service?->name ?: 'Digital service');
  $amount=number_format(((int)$tx->total_minor)/100,2);
  $recipient=$this->mask((string)$tx->recipient);
  $reference=(string)$tx->reference;
  $lines=[
   'SEMIZZY ONE Transaction Update',
   'Reference: '.$reference,
   'Service: '.$service,
   'Amount: NGN '.$amount,
   'Recipient: '.$recipient,
   'Status: '.strtoupper($status),
   'Time: '.now()->format('Y-m-d H:i'),
  ];
  if($status==='successful')$lines[]='Your transaction completed successfully.';
  elseif(in_array($status,['pending','processing'],true))$lines[]='Your transaction is still being processed. No duplicate purchase is required.';
  elseif($status==='failed')$lines[]='The transaction failed. Check the website for the failure reason and next action.';
  elseif($status==='cancelled')$lines[]='The transaction was cancelled. Your reserved funds were released according to the transaction record.';
  elseif($status==='reversed')$lines[]='The transaction was refunded/reversed. Check your wallet and transaction history on the website.';
  return implode("\n",$lines);
 }

 private function canonicalPhone(string $phone): ?string
 {
  $p=preg_replace('/[^0-9+]/','',$phone);
  if(!$p)return null;
  if(str_starts_with($p,'234'))return '+'.$p;
  if(str_starts_with($p,'0'))return '+234'.substr($p,1);
  return str_starts_with($p,'+')?$p:null;
 }

 private function mask(string $value): string
 {
  $v=trim($value);
  if($v==='')return '-';
  $digits=preg_replace('/\D+/','',$v);
  if(strlen($digits)>=7)return substr($digits,0,3).'****'.substr($digits,-3);
  return '****';
 }
}
