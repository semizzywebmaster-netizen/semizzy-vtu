<?php
namespace Semizzy\Addons\Social\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Semizzy\Addons\Social\Models\SocialAccountInventory;
use Semizzy\Addons\Social\Models\SocialNumberInventory;
use Semizzy\Addons\Social\Models\SocialServiceOrder;
use Semizzy\Addons\Social\Models\SocialNumberSms;
use App\Services\Providers\ProviderManager;

final class SocialServicesService {
 public function __construct(private ProviderManager $providers){}
 public function createAccountOrder(int $userId,int $inventoryId): SocialServiceOrder {
  return DB::transaction(function()use($userId,$inventoryId){
   $item=SocialAccountInventory::query()->whereKey($inventoryId)->lockForUpdate()->firstOrFail();
   if($item->status!=='available')throw new RuntimeException('Social account is no longer available.');
   $ref='SOC-A-'.Str::upper(Str::random(20));
   $order=SocialServiceOrder::create(['reference'=>$ref,'user_id'=>$userId,'order_type'=>'account','inventory_id'=>$item->id,'status'=>'pending_payment','amount'=>$item->price,'currency'=>$item->currency,'metadata'=>['platform'=>$item->platform]]);
   return $order;
  });
 }
 public function createNumberOrder(int $userId,int $inventoryId): SocialServiceOrder {
  return DB::transaction(function()use($userId,$inventoryId){
   $item=SocialNumberInventory::query()->whereKey($inventoryId)->lockForUpdate()->firstOrFail();
   if($item->status!=='available')throw new RuntimeException('Verification number is no longer available.');
   $item->status='reserved'; $item->save();
   $order=SocialServiceOrder::create(['reference'=>'SOC-N-'.Str::upper(Str::random(20)),'user_id'=>$userId,'order_type'=>'number','inventory_id'=>$item->id,'status'=>'pending_payment','amount'=>$item->price,'currency'=>$item->currency,'expires_at'=>$item->expires_at,'metadata'=>['country_code'=>$item->country_code,'service_key'=>$item->service_key]]);
   return $order;
  });
 }
 public function purchaseViaProvider(SocialServiceOrder $order): SocialServiceOrder {
  if($order->status!=='paid')throw new RuntimeException('Order must be paid before provider fulfillment.');
  $operation=$order->order_type==='account'?'social_account_purchase':'foreign_number_purchase';
  $payload=['reference'=>$order->reference,'inventory_id'=>$order->inventory_id,'metadata'=>$order->metadata]; if($order->order_type==='number'){ $item=SocialNumberInventory::query()->findOrFail($order->inventory_id); $payload['country_code']=$item->country_code; $payload['country_name']=$item->country_name; $payload['service_key']=$item->service_key; }
  $result=$this->providers->execute($order->order_type==='account'?'social_account':'foreign_number',$operation,$payload,$order->reference);
  $status=strtoupper((string)$result->status);
  $order->provider_id=$result->providerId ?: $order->provider_id;
  $order->provider_reference=$result->providerReference ?: $order->provider_reference;
  $order->metadata=array_merge((array)$order->metadata,['provider_status'=>$status,'message'=>$result->message]);
  if($result->accepted || in_array($status,['SUCCESS','SUCCESSFUL','COMPLETED','VERIFIED'],true)){
   $order->status='fulfilled'; $order->delivered_at=now();
  } elseif(in_array($status,['FAILED','REJECTED','INVALID'],true)){ $order->status='failed'; }
  else { $order->status='processing'; }
  $order->save(); return $order->fresh();
 }
 public function requeryNumber(SocialServiceOrder $order): SocialServiceOrder {
  if($order->order_type!=='number') throw new RuntimeException('Requery is only available for verification-number orders.');
  if(!$order->provider_id) throw new RuntimeException('No provider is recorded for this order; automatic requery is unavailable.');
  if(!$order->provider_reference) throw new RuntimeException('No provider reference is recorded for this order.');
  $provider=\\App\\Models\\ApiProvider::query()->whereKey((int)$order->provider_id)->firstOrFail();
  $item=SocialNumberInventory::query()->find($order->inventory_id);
  $payload=['reference'=>$order->reference,'provider_reference'=>$order->provider_reference,'inventory_id'=>$order->inventory_id,'metadata'=>$order->metadata];
  if($item){$payload['country_code']=$item->country_code;$payload['country_name']=$item->country_name;$payload['service_key']=$item->service_key;$payload['phone_number']=$item->phone_number;}
  $result=$this->providers->executeProvider($provider,'foreign_number','foreign_number_status',$payload,$order->reference.':status');
  $status=strtoupper((string)$result->status);
  $order->provider_id=$result->providerId ?: $order->provider_id;
  $order->provider_reference=$result->providerReference ?: $order->provider_reference;
  $order->metadata=array_merge((array)$order->metadata,['last_requery_status'=>$status,'last_requery_message'=>$result->message,'last_requery_at'=>now()->toIso8601String()]);
  if($result->accepted || in_array($status,['SUCCESS','SUCCESSFUL','COMPLETED','VERIFIED','ACTIVE'],true)){
   $order->status='fulfilled'; $order->delivered_at=$order->delivered_at ?: now();
  } elseif(in_array($status,['FAILED','REJECTED','INVALID','EXPIRED','CANCELLED'],true)){
   $order->status='failed';
  } elseif(in_array($status,['UNKNOWN','PENDING','PROCESSING'],true)){
   $order->status='processing';
  }
  $order->save(); return $order->fresh();
 }
 public function canReceiveSms(SocialServiceOrder $order): bool { return $order->order_type==='number' && in_array($order->status,['paid','processing','fulfilled'],true) && (!$order->expires_at || !$order->expires_at->isPast()); }

 public function ingestSms(SocialServiceOrder $order,string $message,?string $sender=null,?string $providerMessageId=null,array $metadata=[]): SocialNumberSms {
  if($order->order_type!=='number')throw new RuntimeException('SMS can only be attached to a verification-number order.');
  if(!$this->canReceiveSms($order))throw new RuntimeException('Number order is not active or has expired.');
  if($providerMessageId && SocialNumberSms::where('provider_message_id',$providerMessageId)->exists())return SocialNumberSms::where('provider_message_id',$providerMessageId)->firstOrFail();
  return SocialNumberSms::create(['order_id'=>$order->id,'sender'=>$sender,'message'=>$message,'provider_message_id'=>$providerMessageId,'received_at'=>now(),'metadata'=>$metadata]);
 } public function payFromWallet(\App\Models\User $user, SocialServiceOrder $order): SocialServiceOrder {
  return DB::transaction(function () use ($user,$order) {
   $o=SocialServiceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
   if((int)$o->user_id!==$user->id) throw new RuntimeException('Order ownership mismatch.');
   if($o->payment_status==='paid') return $o->fresh();
   if($o->status!=='pending_payment') throw new RuntimeException('Order is not awaiting payment.');
   $wallet=\App\Models\WalletAccount::query()->where('user_id',$user->id)->where('currency',$o->currency)->lockForUpdate()->first();
   if(!$wallet || $wallet->status!=='active') throw new RuntimeException('Active wallet not found.');
   $amount=$this->toMinor((string)$o->amount); $before=(string)$wallet->available_minor;
   if($this->compare($before,$amount)<0) throw new RuntimeException('Insufficient wallet balance.');
   $after=$this->subtract($before,$amount); $wallet->available_minor=$after; $wallet->saveOrFail();
   $ref='SOC-P-'.Str::upper(Str::random(24));
   \App\Models\WalletMovement::create(['wallet_account_id'=>$wallet->id,'operation_key'=>'social:payment:'.$o->reference,'reference'=>$ref,'type'=>'social_service_payment','amount_minor'=>$amount,'currency'=>$wallet->currency,'available_before_minor'=>$before,'available_after_minor'=>$after,'held_before_minor'=>(string)$wallet->held_minor,'held_after_minor'=>(string)$wallet->held_minor,'metadata'=>['order_id'=>$o->id,'order_reference'=>$o->reference]]);
   $o->payment_reference=$ref; $o->payment_status='paid'; $o->paid_at=now(); $o->status='paid'; $o->save();
   return $o->fresh();
  });
 }
 private function toMinor(string $major): string { $major=trim($major); if(!preg_match('/^\d+(?:\.\d{1,2})?$/',$major)) throw new RuntimeException('Invalid order amount.'); [$w,$f]=array_pad(explode('.',$major,2),2,''); $m=ltrim($w.str_pad($f,2,'0'),'0')?:'0'; if($m==='0') throw new RuntimeException('Order amount must be positive.'); return $m; }
 private function compare(string $a,string $b): int { $a=ltrim($a,'0')?:'0'; $b=ltrim($b,'0')?:'0'; return strlen($a)<=>strlen($b) ?: strcmp($a,$b); }
 private function subtract(string $a,string $b): string { if(function_exists('bcsub')) return bcsub($a,$b,0); if(strlen(ltrim($a,'0')?:'0')>17) throw new RuntimeException('Large wallet amounts require BCMath.'); return (string)((int)$a-(int)$b); }

}