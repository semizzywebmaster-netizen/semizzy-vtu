<?php
namespace Semizzy\Addons\Smm\Services;

use App\Services\Providers\ProviderManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Semizzy\Addons\Smm\Models\SmmOrder;
use Semizzy\Addons\Smm\Models\SmmService;

final class SmmOrderService
{
 public function __construct(private ProviderManager $providers, private SmmWalletService $wallets){}
 public function requery(SmmOrder $order): SmmOrder
 {
  $order->refresh();
  if (in_array(strtolower($order->status), ['completed','failed','cancelled'], true)) return $order;
  if (!$order->provider_reference) throw new RuntimeException('Provider reference is required before requery.');
  $service=$order->service()->firstOrFail();
  $result=$this->providers->execute($service->service_key,'smm_requery',['provider_reference'=>$order->provider_reference,'reference'=>$order->reference,'service_id'=>$service->service_key],$order->idempotency_key.':requery');
  return $this->applyProviderResult($order,$result);
 }

 public function cancel(SmmOrder $order): SmmOrder
 {
  $order->refresh();
  if (!in_array(strtolower($order->status), ['pending','processing','accepted','cancel_requested'], true)) return $order;
  if (!$order->provider_reference) throw new RuntimeException('Provider reference is required before cancellation.');
  $service=$order->service()->firstOrFail();
  $result=$this->providers->execute($service->service_key,'smm_cancel',['provider_reference'=>$order->provider_reference,'reference'=>$order->reference],$order->idempotency_key.':cancel');
  $status=strtoupper((string)$result->status);
  if ($result->accepted || in_array($status,['CANCELLED','CANCELED','REFUNDED'],true)) {
   $order->status='cancelled'; $this->wallets->settle($order,false); $order->metadata=array_merge((array)$order->metadata,['cancel'=>$result->message]); $order->save();
  } elseif (in_array($status,['UNKNOWN','PENDING','PROCESSING','IN_PROGRESS'],true)) {
   $order->status='cancel_requested'; $order->metadata=array_merge((array)$order->metadata,['cancel_status'=>$status]); $order->save();
  }
  return $order->fresh();
 }

 private function applyProviderResult(SmmOrder $order, $result): SmmOrder
 {
  $status=strtoupper((string)$result->status);
  $order->provider_reference=$result->providerReference ?: $order->provider_reference;
  $order->metadata=array_merge((array)$order->metadata,['requery_status'=>$status,'message'=>$result->message]);
  if ($result->accepted || in_array($status,['SUCCESS','SUCCESSFUL','ACCEPTED','COMPLETED'],true)) {
   $order->status='completed'; $order->completed_at=$order->completed_at ?: now(); $this->wallets->settle($order,true);
  } elseif (in_array($status,['FAILED','REJECTED','ERROR'],true)) {
   $order->status='failed'; $this->wallets->settle($order,false);
  } else {
   $order->status='pending';
  }
  $order->save();
  return $order->fresh();
 }

 public function create(int $userId,int $serviceId,int $quantity,string $target,string $idempotencyKey): SmmOrder
 {
  $order=DB::transaction(function() use($userId,$serviceId,$quantity,$target,$idempotencyKey){
   $existing=SmmOrder::where('user_id',$userId)->where('idempotency_key',$idempotencyKey)->lockForUpdate()->first();
   if($existing){
    if((int)$existing->service_id!==$serviceId || (int)$existing->quantity!==$quantity || (string)$existing->target!==trim($target)){
     throw new RuntimeException('Idempotency key has already been used for a different SMM order.');
    }
    return $existing;
   }
   $service=SmmService::whereKey($serviceId)->where('active',true)->lockForUpdate()->firstOrFail();
   if($quantity<$service->min_quantity||$quantity>$service->max_quantity) throw new RuntimeException('Quantity is outside the service limits.');
   if($service->mode!=='provider_api') throw new RuntimeException('This SMM service requires an admin/manual workflow.');
   if(!preg_match('/^\d+$/',(string)$service->unit_price_minor)) throw new RuntimeException('Invalid service price.');
   $amount=$this->multiply((string)$service->unit_price_minor,(string)$quantity);
   $wallet=$this->wallets->walletForOrderUser($userId,(string)$service->currency);
   $order=SmmOrder::create(['user_id'=>$userId,'wallet_account_id'=>$wallet->id,'service_id'=>$service->id,'reference'=>'SMM-'.str()->upper(Str::random(20)),'idempotency_key'=>$idempotencyKey,'quantity'=>$quantity,'amount_minor'=>$amount,'currency'=>$service->currency,'status'=>'pending','target'=>trim($target)]);

   $this->wallets->reserve($order);
   return $order;
  });
  if($order->status!=='pending') return $order;
  try {
   $service=$order->service()->firstOrFail();
   $result=$this->providers->execute($service->service_key,'smm_order',['target'=>$order->target,'quantity'=>$order->quantity,'service_id'=>$service->service_key],$idempotencyKey);
   $status=strtoupper((string)$result->status);
   $final=in_array($status,['SUCCESS','SUCCESSFUL','ACCEPTED','COMPLETED'],true);
   $ambiguous=in_array($status,['UNKNOWN','PENDING','PROCESSING','IN_PROGRESS'],true)||(!$result->accepted&&!$final);
   $order->provider_reference=$result->providerReference;
   $order->provider_status=$result->status;
   $order->status=$ambiguous?'pending':strtolower($status);
   $order->processed_at=now();
   $order->metadata=['provider_id'=>$result->providerId,'message'=>$result->message,'financial_state'=>$ambiguous?'held':'settled'];
   if($final){$order->completed_at=now();$this->wallets->settle($order,true);} elseif(!$ambiguous){$this->wallets->settle($order,false);}
   $order->save();
   return $order->fresh();
  } catch(\Throwable $e) {
   $order->refresh();
   $order->status='pending';
   $order->failure_message='Provider outcome is uncertain; wallet funds remain held until requery confirms the final state.';
   $order->metadata=array_merge((array)$order->metadata,['provider_exception'=>$e->getMessage(),'financial_state'=>'held','requery_required'=>true]);
   $order->save();
   return $order->fresh();
  }
 }
 private function multiply(string $a,string $b):string {
  if(function_exists('bcmul')) return bcmul($a,$b,0);
  $a=ltrim($a,'0')?:'0'; $b=ltrim($b,'0')?:'0'; if($a==='0'||$b==='0')return '0';
  $out=array_fill(0,strlen($a)+strlen($b),0);
  for($i=strlen($a)-1;$i>=0;$i--)for($j=strlen($b)-1;$j>=0;$j--){$k=$i+$j+1;$v=$out[$k]+((ord($a[$i])-48)*(ord($b[$j])-48));$out[$k]=$v%10;$out[$k-1]+=intdiv($v,10);}
  return ltrim(implode('',$out),'0')?:'0';
 }
}