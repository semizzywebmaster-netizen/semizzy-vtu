<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceProduct;
use App\Models\VtuTransaction;
use App\Models\VtuBulkOperation;
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
   'mappings'=>ProviderServiceMapping::query()->where(function($q){$q->whereHas('service.category',fn($c)=>$c->where('key','vtu-digital-services'))->orWhere(function($legacy){$legacy->whereNull('service_id')->whereIn('service_key',array_keys(VtuServiceRegistry::MANIFEST));});})->with(['provider','service'])->orderBy('api_provider_id')->orderBy('service_key')->paginate(50)->withQueryString(),
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
  $service=Service::with('category')->findOrFail($data['service_id']);
  if(!$service->category || $service->category->key!=='vtu-digital-services')return back()->with('error','Only VTU services can have VTU provider mappings.');
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
 public function enableProduct(ServiceProduct $product){if(!$product->service || !$product->service->category || $product->service->category->key!=='vtu-digital-services')return back()->with('error','Only VTU products can be managed here.');if(!$product->service->enabled)return back()->with('error','Enable the VTU service before enabling its product.');$product->update(['enabled'=>true]);return back()->with('success','Product enabled.');}
 public function disableProduct(ServiceProduct $product){if(!$product->service || !$product->service->category || $product->service->category->key!=='vtu-digital-services')return back()->with('error','Only VTU products can be managed here.');$product->update(['enabled'=>false]);return back()->with('success','Product disabled.');}
 public function bulkOperations(Request $r){
  $q=VtuBulkOperation::with(['user','service'])->latest();
  if($r->filled('status')){
   $allowed=['processing','pending','partial','successful','failed'];
   $status=(string)$r->input('status');
   if(in_array($status,$allowed,true))$q->where('status',$status);else$q->whereRaw('1=0');
  }
  if($r->filled('reference'))$q->where('reference','like','%'.addcslashes((string)$r->input('reference'),'\\%_').'%');
  return Inertia::render('Admin/VTU/BulkOperations',['operations'=>$q->paginate(50)->withQueryString()]);
 }
 public function reconcileBulk(VtuBulkOperation $bulk, VtuTransactionService $service){
  $items=$bulk->items()->with('transaction')->whereIn('status',['pending','processing'])->whereNotNull('vtu_transaction_id')->limit(50)->get();
  $attempted=0;$reconciled=0;
  foreach($items as $item){
   if(!$item->transaction || !$item->transaction->provider_reference)continue;
   $attempted++;
   try{
    $tx=$service->requery($item->transaction);
    if($tx->status!==$item->status)$reconciled++;
   }catch(\Throwable $e){}
  }
  return back()->with('success',"Bulk reconciliation checked {$attempted} item(s); {$reconciled} state change(s) applied.");
 }
 public function transactions(Request $r){$q=VtuTransaction::with(['user','service','product','provider'])->latest();foreach(['status','service_id','api_provider_id','user_id'] as $f)if($r->filled($f))$q->where($f,$r->input($f));return Inertia::render('Admin/VTU/Transactions',['transactions'=>$q->paginate(50)->withQueryString()]);}
 public function requery(VtuTransaction $transaction,VtuTransactionService $s){$s->requery($transaction);return back()->with('success','Transaction requery completed.');}
 public function enableService(Service $s){if(!$s->category || $s->category->key!=='vtu-digital-services')return back()->with('error','Only VTU services can be managed here.');$s->update(['enabled'=>true]);return back()->with('success','Service enabled.');}
 public function disableService(Service $s){if(!$s->category || $s->category->key!=='vtu-digital-services')return back()->with('error','Only VTU services can be managed here.');$s->update(['enabled'=>false]);return back()->with('success','Service disabled.');}
 public function bootstrap(VtuServiceRegistry $r){$r->bootstrapCatalogue();return back()->with('success','VTU service registry synchronized.');}
}