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

class VtuAdminController extends Controller
{
 public function dashboard(){
  $vtuServices=Service::query()->whereHas('category',fn($q)=>$q->where('key','vtu-digital-services'));
  $vtuServiceIds=(clone $vtuServices)->pluck('id');
  $tx=VtuTransaction::query();
  $recent=VtuTransaction::query()->with(['user:id,name,email','service:id,name','product:id,name','provider:id,display_name'])->latest('created_at')->limit(8)->get(['id','reference','status','amount_minor','currency','user_id','service_id','service_product_id','api_provider_id','created_at','provider_reference','failure_code']);
  $providers=ApiProvider::query()->withCount(['serviceMappings as enabled_mappings_count'=>fn($q)=>$q->where('enabled',true)])->orderBy('priority')->orderBy('display_name')->limit(8)->get(['id','display_name','identifier','priority','enabled','paused','verification_status','integration_status','last_successful_request_at','last_tested_at','last_test_status']);
  $pending=VtuTransaction::whereIn('status',['pending','processing'])->count();
  $refundResolution=VtuTransaction::whereIn('status',['successful','reversed'])->where(function($q){$q->where('metadata->refund_manual_resolution_required',true)->orWhere('metadata->refund_settlement_pending',true);})->count();
  $bulkAttention=VtuBulkOperation::whereIn('status',['pending','processing','partial'])->count();
  $failed24h=VtuTransaction::where('status','failed')->where('created_at','>=',now()->subDay())->count();
  return Inertia::render('Admin/VTU/Dashboard',[
   'metrics'=>['total'=>$tx->count(),'successful'=>(clone $tx)->where('status','successful')->count(),'pending'=>$pending,'failed'=>(clone $tx)->where('status','failed')->count(),'reversed'=>(clone $tx)->where('status','reversed')->count(),'today'=>(clone $tx)->whereDate('created_at',today())->count()],
   'catalogue'=>['services_total'=>(clone $vtuServices)->count(),'services_active'=>(clone $vtuServices)->where('enabled',true)->count(),'products_total'=>ServiceProduct::whereIn('service_id',$vtuServiceIds)->count(),'products_active'=>ServiceProduct::whereIn('service_id',$vtuServiceIds)->where('enabled',true)->count(),'mappings_active'=>ProviderServiceMapping::whereIn('service_id',$vtuServiceIds)->where('enabled',true)->count()],
   'providers'=>['total'=>ApiProvider::count(),'eligible'=>ApiProvider::eligibleForNewTransactions()->count(),'paused'=>ApiProvider::where('paused',true)->count(),'needs_attention'=>ApiProvider::where(fn($q)=>$q->where('enabled',false)->orWhere('paused',true)->orWhere('verification_status','!=','live_verified')->orWhere('integration_status','!=','live_verified'))->count()],
   'attention'=>['pending_transactions'=>$pending,'failed_last_24h'=>$failed24h,'refund_reconciliation'=>$refundResolution,'bulk_operations'=>$bulkAttention],
   'recentTransactions'=>$recent,'providerHealth'=>$providers,
  ]);
 }

 public function services(){
  $services=Service::query()
   ->whereHas('category',fn($q)=>$q->where('key','vtu-digital-services'))
   ->withCount('products')->orderBy('name')->get(['id','key','name','description','enabled','category_id']);
  return Inertia::render('Admin/VTU/Services',['services'=>$services]);
 }

 public function mappings(){
  $mappings=ProviderServiceMapping::query()
   ->whereHas('service.category',fn($q)=>$q->where('key','vtu-digital-services'))
   ->with(['provider:id,display_name,identifier,priority,enabled,paused,verification_status,integration_status','service:id,key,name,enabled'])
   ->latest()->paginate(50)->withQueryString();
  $providers=ApiProvider::query()->orderBy('priority')->orderBy('display_name')->get(['id','display_name','identifier','priority','enabled','paused','verification_status','integration_status']);
  $services=Service::query()->whereHas('category',fn($q)=>$q->where('key','vtu-digital-services'))->orderBy('name')->get(['id','key','name','enabled']);
  return Inertia::render('Admin/VTU/Mappings',compact('mappings','providers','services'));
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
   if(!$provider->enabled || $provider->paused || $provider->verification_status!=='live_verified' || $provider->integration_status!=='live_verified') return back()->with('error','A mapping can only be enabled for an enabled, unpaused, live-verified provider.');
   if(!$service->enabled)return back()->with('error','The VTU service must be enabled before its provider mapping can be enabled.');
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
  if($r->filled('status')){$allowed=['processing','pending','partial','successful','failed'];$status=(string)$r->input('status');if(in_array($status,$allowed,true))$q->where('status',$status);else$q->whereRaw('1=0');}
  if($r->filled('reference'))$q->where('reference','like','%'.addcslashes((string)$r->input('reference'),'\\%_').'%');
  return Inertia::render('Admin/VTU/BulkOperations',['operations'=>$q->paginate(50)->withQueryString()]);
 }
 public function reconcileBulk(VtuBulkOperation $bulk,VtuTransactionService $service){
  $items=$bulk->items()->with('transaction')->whereIn('status',['pending','processing'])->whereNotNull('vtu_transaction_id')->limit(50)->get();$attempted=0;$reconciled=0;
  foreach($items as $item){if(!$item->transaction||!$item->transaction->provider_reference)continue;$attempted++;try{$tx=$service->requery($item->transaction);if($tx->status!==$item->status)$reconciled++;}catch(\Throwable $e){}}
  return back()->with('success',"Bulk reconciliation checked {$attempted} item(s); {$reconciled} state change(s) applied.");
 }
 public function reconcileSelectedBulk(Request $r,VtuTransactionService $service){
  $data=$r->validate(['bulk_ids'=>['required','array','min:1','max:50'],'bulk_ids.*'=>['integer','distinct','exists:vtu_bulk_operations,id']]);
  $attempted=0;$reconciled=0;
  $bulks=VtuBulkOperation::query()->whereIn('id',$data['bulk_ids'])->get();
  foreach($bulks as $bulk){
   $items=$bulk->items()->with('transaction')->whereIn('status',['pending','processing'])->whereNotNull('vtu_transaction_id')->limit(50)->get();
   foreach($items as $item){
    if(!$item->transaction||!$item->transaction->provider_reference)continue;
    $attempted++;
    try{$tx=$service->requery($item->transaction);if($tx->status!==$item->status)$reconciled++;}catch(\\Throwable $e){}
   }
  }
  return back()->with('success',"Selected bulk reconciliation checked {$attempted} item(s); {$reconciled} state change(s) applied.");
 }
 public function transactions(Request $r){
  $q=VtuTransaction::with(['user:id,name,email','service:id,name','product:id,name','provider:id,display_name'])->latest('created_at');
  foreach(['status','service_id','api_provider_id','user_id'] as $f)if($r->filled($f))$q->where($f,$r->input($f));
  $transactions=$q->paginate(50)->withQueryString();
  return Inertia::render('Admin/VTU/Transactions',['transactions'=>$transactions]);
 }
 public function requery(VtuTransaction $transaction,VtuTransactionService $s){$s->requery($transaction);return back()->with('success','Transaction requery completed.');}
 public function refund(Request $r,VtuTransaction $transaction,VtuTransactionService $s){
  $data=$r->validate(['reason'=>['nullable','string','max:500']]);$transaction=$s->refund($transaction,(string)($data['reason']??'Administrative refund'));
  return back()->with($transaction->status==='reversed'?'success':'error',$transaction->status==='reversed'?'Transaction refunded successfully.':($transaction->failure_message??'Refund requires provider reconciliation.'));
 }
 public function enableService(Service $service){$service->loadMissing('category');if(!$service->category||$service->category->key!=='vtu-digital-services')return back()->with('error','Only VTU services can be managed here.');$service->update(['enabled'=>true]);return back()->with('success','Service enabled.');}
 public function disableService(Service $service){$service->loadMissing('category');if(!$service->category||$service->category->key!=='vtu-digital-services')return back()->with('error','Only VTU services can be managed here.');$service->update(['enabled'=>false]);return back()->with('success','Service disabled.');}
 public function bootstrap(VtuServiceRegistry $r){$r->bootstrapCatalogue();return back()->with('success','VTU service registry synchronized.');}
}
