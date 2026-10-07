<?php
namespace Semizzy\Addons\Smm\Services;
use App\Services\Providers\ProviderManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Semizzy\Addons\Smm\Models\SmmOrder;
use Semizzy\Addons\Smm\Models\SmmService;

final class SmmOrderService {
 public function __construct(private ProviderManager $providers){}
 public function create(int $userId,int $serviceId,int $quantity,string $target,string $idempotencyKey): SmmOrder {
  return DB::transaction(function() use($userId,$serviceId,$quantity,$target,$idempotencyKey){
   $service=SmmService::query()->whereKey($serviceId)->where('active',true)->lockForUpdate()->firstOrFail();
   if($quantity<$service->min_quantity||$quantity>$service->max_quantity) throw new RuntimeException('Quantity is outside the service limits.');
   $existing=SmmOrder::query()->where('user_id',$userId)->where('idempotency_key',$idempotencyKey)->first();
   if($existing) return $existing;
   if($service->mode!=='provider_api') throw new RuntimeException('This SMM service requires an admin/manual workflow.');
   $amount=(int)$service->unit_price_minor*$quantity;
   $order=SmmOrder::create(['user_id'=>$userId,'service_id'=>$service->id,'reference'=>'SMM-'.str()->upper(Str::random(20)),'idempotency_key'=>$idempotencyKey,'quantity'=>$quantity,'amount_minor'=>$amount,'currency'=>$service->currency,'status'=>'pending','target'=>$target]);
   $result=$this->providers->execute($service->service_key,'smm_order',['target'=>$target,'quantity'=>$quantity,'service_id'=>$service->service_key],$idempotencyKey);
   $order->provider_reference=$result->providerReference;
   $order->status=strtolower($result->status);
   $order->metadata=['provider_id'=>$result->providerId,'message'=>$result->message];
   if($result->accepted&&in_array(strtoupper($result->status),['SUCCESS','SUCCESSFUL','ACCEPTED','COMPLETED'],true))$order->completed_at=now();
   $order->save();
   return $order;
  });
 }
}