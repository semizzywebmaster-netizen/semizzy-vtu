<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceProduct;
use App\Models\VtuTransaction;
use App\Models\ApiProvider;
use App\Models\ProviderServiceMapping;
use App\Services\Audit\AuditLogger;
use App\Services\Vtu\VtuServiceRegistry;
use App\Services\Vtu\VtuTransactionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
class VtuAdminController extends Controller{
 public function dashboard(){return Inertia::render('Admin/VTU/Dashboard',['metrics'=>['total'=>VtuTransaction::count(),'successful'=>VtuTransaction::where('status','successful')->count(),'pending'=>VtuTransaction::where('status','pending')->count(),'failed'=>VtuTransaction::where('status','failed')->count(),'today'=>VtuTransaction::whereDate('created_at',today())->count()]]);}
 public function services(){return Inertia::render('Admin/VTU/Services',['services'=>Service::whereHas('category',fn($q)=>$q->where('key','vtu-digital-services'))->withCount('products')->orderBy('id')->get()]);}
 public function mappings(){
  return Inertia::render('Admin/VTU/Mappings',[
   'mappings'=>ProviderServiceMapping::with(['provider','service'])->orderBy('api_provider_id')->orderBy('service_key')->paginate(50)->withQueryString(),
   'providers'=>ApiProvider::query()->orderBy('priority')->orderBy('display_name')->get(['id','display_name','identifier','priority','enabled','paused','verification_status','integration_status']),
   'services'=>Service::query()->whereHas('category',fn($q)=>$q->where('key','vtu-digital-services'))->orderBy('name')->get(['id','key','name','enabled']),
  ]);
 }
 public function saveMapping(Request $r, AuditLogger $audit){
  $data=$r->validate([
   'api_provider_id'=>'required|integer|exists:api_providers,id',
   'service_id'=>'required|integer|exists:services,id',
   'provider_service_id'=>'nullable|string|max:160',
   'capabilities'=>'nullable|array',
   'capabilities.*'=>'string|max:80',
   'enabled'=>'nullable|boolean',
  ]);
  $provider=ApiProvider::findOrFail($data['api_provider_id']);
  $service=Service::findOrFail($data['service_id']);
  if($data['enabled']??false){
   if(!$provider->enabled || $provider->paused || $provider->verification_status!=='live_verified' || $provider->integration_status!=='live_verified')
    return back()->with('error','A mapping can only be enabled for an enabled, unpaused, live-verified provider.');
   if(!$service->enabled) return back()->with('error','The VTU service must be enabled before its provider mapping can be enabled.');
  }
  $mapping=ProviderServiceMapping::updateOrCreate(
   ['api_provider_id'=>$provider->id,'service_key'=>$service->key],
   ['service_id'=>$service->id,'provider_service_id'=>$data['provider_service_id']??null,'capabilities'=>$data['capabilities']??[],'enabled'=>$data['enabled']??false]
  );
  $audit->record('vtu.provider_mapping.saved',$mapping,['provider_id'=>$provider->id,'service_id'=>$service->id,'enabled'=>(bool)$mapping->enabled],$r);
  return back()->with('success','Provider service mapping saved.');
 }
 public function products(){return Inertia::render('Admin/VTU/Products',['products'=>ServiceProduct::whereHas('service.category',fn($q)=>$q->where('key','vtu-digital-services'))->with('service')->latest()->paginate(50)]);}
 public function enableProduct(ServiceProduct $product){$product->update(['enabled'=>true]);return back()->with('success','Product enabled.');}
 public function disableProduct(ServiceProduct $product){$product->update(['enabled'=>false]);return back()->with('success','Product disabled.');}
 public function transactions(Request $r){$q=VtuTransaction::with(['user','service','product','provider'])->latest();foreach(['status','service_id','api_provider_id','user_id'] as $f)if($r->filled($f))$q->where($f,$r->input($f));return Inertia::render('Admin/VTU/Transactions',['transactions'=>$q->paginate(50)->withQueryString()]);}
 public function requery(VtuTransaction $t,VtuTransactionService $s){$s->requery($t);return back()->with('success','Transaction requery completed.');}
 public function enableService(Service $s){$s->update(['enabled'=>true]);return back()->with('success','Service enabled.');}
 public function disableService(Service $s){$s->update(['enabled'=>false]);return back()->with('success','Service disabled.');}
 public function bootstrap(VtuServiceRegistry $r){$r->bootstrapCatalogue();return back()->with('success','VTU service registry synchronized.');}
}