<?php
namespace Semizzy\Addons\Government\Services;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Semizzy\Addons\Government\Models\GovernmentService;
use Semizzy\Addons\Government\Models\GovernmentApplication;
class GovernmentServicesService {
 public function createApplication(int $userId,GovernmentService $service,array $data=[]): GovernmentApplication {
  if($service->status!=='active') throw new \RuntimeException('Government service is unavailable.');
  return DB::transaction(function()use($userId,$service,$data){
   return GovernmentApplication::create([
    'reference'=>'GOV-'.strtoupper(Str::random(12)),
    'user_id'=>$userId,'service_id'=>$service->id,'status'=>'draft',
    'amount'=>$service->price,'currency'=>$service->currency,
    'application_data'=>$data,'metadata'=>['fulfillment_mode'=>$service->fulfillment_mode],
   ]);
  });
 }
}