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
 public function create(int $userId,int $serviceId,int $quantity,string $target,string $idempotencyKey): SmmOrder
 {
  $order=DB::transaction(function() use($userId,$serviceId,$quantity,$target,$idempotencyKey){
   $existing=SmmOrder::where('user_id',$userId)->where('idempotency_key',$idempotencyKey)->first();
   if($existing) return $existing;
   $service=SmmService::whereKey($serviceId)->where('active',true)->lockForUpdate()->firstOrFail();
   if($quantity<$service->min_quantity||$quantity>$service->max_quantity) throw new RuntimeException('Quantity is outside the service limits.');
   if($service->mode!=='provider_api') throw new RuntimeException('This SMM service requires an admin/manual workflow.');
   if(!preg_match('/^\d+$/',(string)$service->unit_price_minor)) throw new RuntimeException('Invalid service price.');
   $amount=$this->multiply((string)$service->unit_price_minor,(string)$quantity);
   $order=SmmOrder::create(['user_id'=>$userId,'service_id'=>$service->id,'reference'=>'SMM-'.str()->upper(Str::random(20)),'idempotency_key'=>$idempotencyKey,'quantity'=>$quantity,'amount_minor'=>$amount,'currency'=>$service->currency,'status'=>'pending','target'=>trim($target)]);
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
   $order->status=$ambiguous?'pending':strtolower($status);
   $order->metadata=['provider_id'=>$result->providerId,'message'=>$result->message];
   if($final){$order->completed_at=now();$this->wallets->settle($order,true);} elseif(!$ambiguous){$this->wallets->settle($order,false);}
   $order->save();
   return $order->fresh();
  } catch(\Throwable $e) {
   $order->refresh();
   try{$this->wallets->settle($order,false);}catch(\Throwable $settlementError){$order->metadata=['settlement_error'=>$settlementError->getMessage()];$order->save();throw $settlementError;}
   $order->status='failed'; $order->metadata=['error'=>$e->getMessage()]; $order->save(); throw $e;
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