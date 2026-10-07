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
  $payload=['reference'=>$order->reference,'inventory_id'=>$order->inventory_id,'metadata'=>$order->metadata];
  $result=$this->providers->execute($order->order_type==='account'?'social_account':'foreign_number',$operation,$payload,$order->reference);
  $status=strtoupper((string)$result->status);
  $order->provider_reference=$result->providerReference ?: $order->provider_reference;
  $order->metadata=array_merge((array)$order->metadata,['provider_status'=>$status,'message'=>$result->message]);
  if($result->accepted || in_array($status,['SUCCESS','SUCCESSFUL','COMPLETED','VERIFIED'],true)){
   $order->status='fulfilled'; $order->delivered_at=now();
  } elseif(in_array($status,['FAILED','REJECTED','INVALID'],true)){ $order->status='failed'; }
  else { $order->status='processing'; }
  $order->save(); return $order->fresh();
 }
 public function ingestSms(SocialServiceOrder $order,string $message,?string $sender=null,?string $providerMessageId=null,array $metadata=[]): SocialNumberSms {
  if($order->order_type!=='number')throw new RuntimeException('SMS can only be attached to a verification-number order.');
  if(!in_array($order->status,['paid','processing','fulfilled'],true))throw new RuntimeException('Number order is not active.');
  if($providerMessageId && SocialNumberSms::where('provider_message_id',$providerMessageId)->exists())return SocialNumberSms::where('provider_message_id',$providerMessageId)->firstOrFail();
  return SocialNumberSms::create(['order_id'=>$order->id,'sender'=>$sender,'message'=>$message,'provider_message_id'=>$providerMessageId,'received_at'=>now(),'metadata'=>$metadata]);
 }
}