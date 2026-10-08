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
use App\Services\Vtu\VtuBulkService;
use App\Services\Vtu\VtuBulkReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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

 public function bulkToggleMappings(Request $r, AuditLogger $audit): JsonResponse|\Illuminate\Http\RedirectResponse{
  $data=$r->validate(['mapping_ids'=>['required','array','min:1','max:100'],'mapping_ids.*'=>['integer','distinct','exists:provider_service_mappings,id'],'enabled'=>['required','boolean']]);$changed=0;$skipped=0;
  foreach(ProviderServiceMapping::query()->whereIn('id',$data['mapping_ids'])->with(['provider','service.category'])->get() as $mapping){
   try{
    $provider=$mapping->provider;$service=$mapping->service;
    if(!$provider||!$service||!$service->category||$service->category->key!=='vtu-digital-services'||($data['enabled']&&(!$provider->enabled||$provider->paused||$provider->verification_status!=='live_verified'||$provider->integration_status!=='live_verified'||!$service->enabled))){$skipped++;continue;}
    $mapping->updateOrFail(['enabled'=>$data['enabled']]);$changed++;
    try{$audit->record($data['enabled']?'vtu.provider_mapping.enabled':'vtu.provider_mapping.disabled',$mapping,['bulk'=>true],$r);}catch(\Throwable $auditException){report($auditException);}
   }catch(\Throwable $e){report($e);$skipped++;}
  }
  $message="Bulk mapping update completed: {$changed} changed, {$skipped} skipped."; return $r->expectsJson()?response()->json(['status'=>'completed','changed'=>$changed,'skipped'=>$skipped,'message'=>$message]):back()->with('success',$message);
 }

 public function saveMapping(Request $r, AuditLogger $audit): JsonResponse|\Illuminate\Http\RedirectResponse{
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
  if(!$service->category || $service->category->key!=='vtu-digital-services'){ $message='Only VTU services can have VTU provider mappings.'; return $r->expectsJson()?response()->json(['message'=>$message],422):back()->with('error',$message); }
  if($data['enabled']??false){
   if(!$provider->enabled || $provider->paused || $provider->verification_status!=='live_verified' || $provider->integration_status!=='live_verified'){ $message='A mapping can only be enabled for an enabled, unpaused, live-verified provider.'; return $r->expectsJson()?response()->json(['message'=>$message],422):back()->with('error',$message); }
   if(!$service->enabled){ $message='The VTU service must be enabled before its provider mapping can be enabled.'; return $r->expectsJson()?response()->json(['message'=>$message],422):back()->with('error',$message); }
  }
  $mapping=ProviderServiceMapping::updateOrCreate(
   ['api_provider_id'=>$provider->id,'service_key'=>$service->key],
   ['service_id'=>$service->id,'provider_service_id'=>$data['provider_service_id']??null,'capabilities'=>$data['capabilities']??[],'enabled'=>$data['enabled']??false]
  );
  $audit->record('vtu.provider_mapping.saved',$mapping,['provider_id'=>$provider->id,'service_id'=>$service->id,'enabled'=>(bool)$mapping->enabled],$r);
  $message='Provider service mapping saved.';
  return $r->expectsJson()?response()->json(['status'=>'completed','message'=>$message,'mapping_id'=>$mapping->id]):back()->with('success',$message);
 }

 public function products(){return Inertia::render('Admin/VTU/Products',['products'=>ServiceProduct::whereHas('service.category',fn($q)=>$q->where('key','vtu-digital-services'))->with('service')->latest()->paginate(50)]);}
 public function bulkToggleServices(Request $r, AuditLogger $audit): JsonResponse|\Illuminate\Http\RedirectResponse{
  $data=$r->validate(['service_ids'=>['required','array','min:1','max:100'],'service_ids.*'=>['integer','distinct','exists:services,id'],'enabled'=>['required','boolean']]);$changed=0;$skipped=0;
  foreach(Service::query()->whereIn('id',$data['service_ids'])->with('category')->get() as $service){
   try{
    if(!$service->category||$service->category->key!=='vtu-digital-services'){ $skipped++; continue; }
    $service->updateOrFail(['enabled'=>$data['enabled']]);$changed++;
    try{$audit->record($data['enabled']?'vtu.service.enabled':'vtu.service.disabled',$service,['bulk'=>true],$r);}catch(\Throwable $auditException){report($auditException);}
   }catch(\Throwable $e){report($e);$skipped++;}
  }
  $message="Bulk service status update completed: {$changed} changed, {$skipped} skipped."; return $r->expectsJson()?response()->json(['status'=>'completed','changed'=>$changed,'skipped'=>$skipped,'message'=>$message]):back()->with('success',$message);
 }
 public function bulkToggleProducts(Request $r, AuditLogger $audit): JsonResponse|\Illuminate\Http\RedirectResponse{
  $data=$r->validate(['product_ids'=>['required','array','min:1','max:100'],'product_ids.*'=>['integer','distinct','exists:service_products,id'],'enabled'=>['required','boolean']]);$changed=0;$skipped=0;
  foreach(ServiceProduct::query()->whereIn('id',$data['product_ids'])->with('service.category')->get() as $product){
   try{
    if(!$product->service||!$product->service->category||$product->service->category->key!=='vtu-digital-services'||($data['enabled']&&!$product->service->enabled)){ $skipped++; continue; }
    $product->updateOrFail(['enabled'=>$data['enabled']]);$changed++;
    try{$audit->record($data['enabled']?'vtu.product.enabled':'vtu.product.disabled',$product,['bulk'=>true],$r);}catch(\Throwable $auditException){report($auditException);}
   }catch(\Throwable $e){report($e);$skipped++;}
  }
  $message="Bulk product status update completed: {$changed} changed, {$skipped} skipped."; return $r->expectsJson()?response()->json(['status'=>'completed','changed'=>$changed,'skipped'=>$skipped,'message'=>$message]):back()->with('success',$message);
 }
 public function enableProduct(ServiceProduct $product){if(!$product->service || !$product->service->category || $product->service->category->key!=='vtu-digital-services')return back()->with('error','Only VTU products can be managed here.');if(!$product->service->enabled)return back()->with('error','Enable the VTU service before enabling its product.');try{$product->updateOrFail(['enabled'=>true]);return back()->with('success','Product enabled.');}catch(\Throwable $e){report($e);return back()->with('error','Product could not be enabled safely.');}}
 public function disableProduct(ServiceProduct $product){if(!$product->service || !$product->service->category || $product->service->category->key!=='vtu-digital-services')return back()->with('error','Only VTU products can be managed here.');try{$product->updateOrFail(['enabled'=>false]);return back()->with('success','Product disabled.');}catch(\Throwable $e){report($e);return back()->with('error','Product could not be disabled safely.');}}
 public function exportBulk(Request $r): \Symfony\\Component\\HttpFoundation\\StreamedResponse{
  $q=VtuBulkOperation::query()->with(['user','items.transaction','items.product'])->latest('id');
  if($r->filled('status')){$status=(string)$r->input('status');if(in_array($status,['processing','pending','partial','successful','failed','cancelled'],true))$q->where('status',$status);}
  if($r->filled('reference'))$q->where('reference','like','%'.addcslashes((string)$r->input('reference'),'\\%_').'%');
  $filename='vtu-bulk-reconciliation-'.now()->format('Ymd-His').'.csv';
  return response()->streamDownload(function()use($q){
   $out=fopen('php://output','w');
   fputcsv($out,['Bulk Reference','Bulk Status','User','Item ID','Sequence','Item Status','Recipient','Product','Amount Minor','Provider Reference','Transaction Reference','Failure Reason','Created At','Updated At']);
   $q->chunkById(100,function($bulks)use($out){
    foreach($bulks as $bulk)foreach($bulk->items as $item){
     $tx=$item->transaction;
     fputcsv($out,[$bulk->reference,$bulk->status,$bulk->user?->email??$bulk->user?->name??'', $item->id,$item->sequence,$item->status,$item->recipient??'', $item->product?->name??'', $item->amount_minor??'', $tx?->provider_reference??'', $tx?->reference??'', $item->error_message??$tx?->failure_message??'', $item->created_at?->toIso8601String()??'', $item->updated_at?->toIso8601String()??'']);
    }
   });
   fclose($out);
  },$filename,['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'no-store']);
 }

 public function bulkReport(VtuBulkOperation $bulk): \Symfony\\Component\\HttpFoundation\\StreamedResponse{
  $bulk->load(['user','items.transaction','items.product']);
  $counts=['pending'=>0,'processing'=>0,'successful'=>0,'failed'=>0,'cancelled'=>0,'other'=>0];
  foreach($bulk->items as $item){$key=(string)$item->status;if(array_key_exists($key,$counts))$counts[$key]++;else$counts['other']++;}
  $filename='vtu-bulk-report-'.$bulk->reference.'-'.now()->format('Ymd-His').'.csv';
  return response()->streamDownload(function()use($bulk,$counts){
   $out=fopen('php://output','w');
   fputcsv($out,['VTU BULK OPERATION REPORT']);
   fputcsv($out,['Bulk Reference',$bulk->reference]);
   fputcsv($out,['Bulk Status',$bulk->status]);
   fputcsv($out,['User',$bulk->user?->email??$bulk->user?->name??'']);
   fputcsv($out,['Total Items',$bulk->items->count()]);
   foreach($counts as $status=>$count)fputcsv($out,['Items '.ucfirst($status),$count]);
   fputcsv($out,[]);
   fputcsv($out,['Item ID','Sequence','Status','Recipient','Product','Amount Minor','Provider Reference','Transaction Reference','Failure Reason','Created At','Updated At']);
   foreach($bulk->items as $item){$tx=$item->transaction;fputcsv($out,[$item->id,$item->sequence,$item->status,$item->recipient??'', $item->product?->name??'', $item->amount_minor??'', $tx?->provider_reference??'', $tx?->reference??'', $item->error_message??$tx?->failure_message??'', $item->created_at?->toIso8601String()??'', $item->updated_at?->toIso8601String()??'']);}
   fclose($out);
  },$filename,['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'no-store']);
 }

 public function bulkOperations(Request $r){
  $q=VtuBulkOperation::with(['user','service'])->latest();
  if($r->filled('status')){$allowed=['processing','pending','partial','successful','failed'];$status=(string)$r->input('status');if(in_array($status,$allowed,true))$q->where('status',$status);else$q->whereRaw('1=0');}
  if($r->filled('reference'))$q->where('reference','like','%'.addcslashes((string)$r->input('reference'),'\\%_').'%');
  return Inertia::render('Admin/VTU/BulkOperations',['operations'=>$q->paginate(50)->withQueryString()]);
 }
 public function reconcileBulk(VtuBulkOperation $bulk,VtuTransactionService $service,VtuBulkService $bulkService){

  $items=$bulk->items()->with('transaction')->whereIn('status',['pending','processing'])->whereNotNull('vtu_transaction_id')->limit(50)->get();$attempted=0;$reconciled=0;
  foreach($items as $item){if(!$item->transaction||!$item->transaction->provider_reference)continue;$attempted++;try{$tx=$service->requery($item->transaction);if($tx->status!==$item->status)$reconciled++;}catch(\Throwable $e){}}
  $bulkService->recalculate($bulk);
  return back()->with('success',"Bulk reconciliation checked {$attempted} item(s); {$reconciled} state change(s) applied.");
 }
 public function requeryBulkItem(Request $r,VtuBulkOperation $bulk,VtuBulkService $bulkService,VtuTransactionService $service): JsonResponse|\\Illuminate\\Http\\RedirectResponse{
  $data=$r->validate(['item_ids'=>['nullable','array','max:100'],'item_ids.*'=>['integer','distinct']]);
  $query=$bulk->items()->with('transaction')->whereIn('status',['pending','processing']);
  if(!empty($data['item_ids'])) $query->whereIn('id',$data['item_ids']);
  $items=$query->limit(100)->get();
  $checked=0;$changed=0;$skipped=0;
  foreach($items as $item){
   if(!$item->transaction||!$item->transaction->provider_reference||$item->transaction->isTerminal()){$skipped++;continue;}
   $checked++;
   try{$before=$item->transaction->status;$after=$service->requery($item->transaction);if($after->status!==$before)$changed++;$bulkService->recalculate($bulk);}catch(\\Throwable $e){$skipped++;report($e);}
  }
  $message="Bulk item requery checked {$checked}; {$changed} state change(s), {$skipped} skipped/failed.";
  return $r->expectsJson()?response()->json(['status'=>'completed','checked'=>$checked,'changed'=>$changed,'skipped'=>$skipped,'message'=>$message]):back()->with('success',$message);
 }

 public function cancelBulk(Request $r,VtuBulkOperation $bulk,VtuBulkService $bulkService): JsonResponse{
  $data=$r->validate(['reason'=>['required','string','max:500']]);
  $result=$bulkService->cancelAdmin($bulk,(int)$r->user()->id,$data['reason']);
  return response()->json(['data'=>$result,'message'=>'Bulk operation cancelled safely.']);
 }

 public function auditBulk(VtuBulkOperation $bulk, VtuBulkReconciliationService $reconciliation): JsonResponse{
  $result=$reconciliation->audit($bulk);
  return response()->json($result, $result['healthy'] ? 200 : 409);
 }

 public function recoverStaleBulk(Request $r, VtuBulkService $bulkService): JsonResponse|\Illuminate\Http\RedirectResponse{
  $data=$r->validate([
   'limit'=>['nullable','integer','min:1','max:100'],
   'stale_minutes'=>['nullable','integer','min:5','max:1440'],
  ]);
  try{
   $count=$bulkService->recoverStaleOperations((int)($data['limit']??50),(int)($data['stale_minutes']??10));
   $message="Bulk recovery checked and reconciled {$count} item(s).";
   return $r->expectsJson()?response()->json(['status'=>'completed','recovered'=>$count,'message'=>$message]):back()->with('success',$message);
  }catch(\\Throwable $e){
   report($e);
   $message='Bulk recovery failed safely. No destructive recovery was applied.';
   return $r->expectsJson()?response()->json(['message'=>$message],500):back()->with('error',$message);
  }
 }

 public function reconcileSelectedBulk(Request $r,VtuTransactionService $service,VtuBulkService $bulkService): JsonResponse|\Illuminate\Http\RedirectResponse{
  $data=$r->validate(['bulk_ids'=>['required','array','min:1','max:50'],'bulk_ids.*'=>['integer','distinct','exists:vtu_bulk_operations,id']]);
  $attempted=0;$reconciled=0;
  $bulks=VtuBulkOperation::query()->whereIn('id',$data['bulk_ids'])->get();
  foreach($bulks as $bulk){
   $items=$bulk->items()->with('transaction')->whereIn('status',['pending','processing'])->whereNotNull('vtu_transaction_id')->limit(50)->get();
   foreach($items as $item){
    if(!$item->transaction||!$item->transaction->provider_reference)continue;
    $attempted++;
    try{$tx=$service->requery($item->transaction);if($tx->status!==$item->status)$reconciled++;}catch(\Throwable $e){}
   }
   try{$bulkService->recalculate($bulk);}catch(\Throwable $e){report($e);}
  }
  $message="Selected bulk reconciliation checked {$attempted} item(s); {$reconciled} state change(s) applied."; return $r->expectsJson()?response()->json(['status'=>'completed','attempted'=>$attempted,'reconciled'=>$reconciled,'message'=>$message]):back()->with('success',$message);
 }
 public function transactions(Request $r){
  $q=VtuTransaction::with(['user:id,name,email','service:id,name','product:id,name','provider:id,display_name'])->latest('created_at');
  foreach(['status','service_id','api_provider_id','user_id'] as $f)if($r->filled($f))$q->where($f,$r->input($f));
  $transactions=$q->paginate(50)->withQueryString();
  return Inertia::render('Admin/VTU/Transactions',['transactions'=>$transactions]);
 }
 public function bulkRequery(Request $r,VtuTransactionService $service): JsonResponse|\Illuminate\Http\RedirectResponse{
  $data=$r->validate(['transaction_ids'=>['required','array','min:1','max:100'],'transaction_ids.*'=>['integer','distinct','exists:vtu_transactions,id']]);$checked=0;$changed=0;$skipped=0;
  foreach(VtuTransaction::query()->whereIn('id',$data['transaction_ids'])->whereIn('status',['pending','processing'])->get() as $tx){if(!$tx->provider_reference){$skipped++;continue;}$checked++;try{$before=$tx->status;$after=$service->requery($tx);if($after->status!==$before)$changed++;}catch(\Throwable $e){$skipped++;}}
  $message="Bulk transaction requery checked {$checked}; {$changed} state change(s), {$skipped} skipped/failed."; return $r->expectsJson()?response()->json(['status'=>'completed','checked'=>$checked,'changed'=>$changed,'skipped'=>$skipped,'message'=>$message]):back()->with('success',$message);
 }

 public function requery(Request $r,VtuTransaction $transaction,VtuTransactionService $s): JsonResponse|\Illuminate\Http\RedirectResponse{try{$s->requery($transaction);$message='Transaction requery completed.';return $r->expectsJson()?response()->json(['status'=>'completed','message'=>$message]):back()->with('success',$message);}catch(\Throwable $e){report($e);$message='Transaction requery failed safely.';return $r->expectsJson()?response()->json(['message'=>$message],500):back()->with('error',$message);}}
 public function refund(Request $r,VtuTransaction $transaction,VtuTransactionService $s){
  $data=$r->validate(['reason'=>['nullable','string','max:500']]);try{$transaction=$s->refund($transaction,(string)($data['reason']??'Administrative refund'));}catch(\Throwable $e){report($e);return back()->with('error','Refund could not be completed safely. Check reconciliation state.');}
  return back()->with($transaction->status==='reversed'?'success':'error',$transaction->status==='reversed'?'Transaction refunded successfully.':($transaction->failure_message??'Refund requires provider reconciliation.'));
 }
 public function enableService(Request $r, Service $service): JsonResponse|\Illuminate\Http\RedirectResponse{$service->loadMissing('category');if(!$service->category||$service->category->key!=='vtu-digital-services'){ $message='Only VTU services can be managed here.'; return $r->expectsJson()?response()->json(['message'=>$message],422):back()->with('error',$message);}try{$service->updateOrFail(['enabled'=>true]);$message='Service enabled.';return $r->expectsJson()?response()->json(['status'=>'completed','message'=>$message]):back()->with('success',$message);}catch(\Throwable $e){report($e);$message='Service could not be enabled safely.';return $r->expectsJson()?response()->json(['message'=>$message],500):back()->with('error',$message);}}
 public function disableService(Request $r, Service $service): JsonResponse|\Illuminate\Http\RedirectResponse{$service->loadMissing('category');if(!$service->category||$service->category->key!=='vtu-digital-services'){ $message='Only VTU services can be managed here.'; return $r->expectsJson()?response()->json(['message'=>$message],422):back()->with('error',$message);}try{$service->updateOrFail(['enabled'=>false]);$message='Service disabled.';return $r->expectsJson()?response()->json(['status'=>'completed','message'=>$message]):back()->with('success',$message);}catch(\Throwable $e){report($e);$message='Service could not be disabled safely.';return $r->expectsJson()?response()->json(['message'=>$message],500):back()->with('error',$message);}}
 public function bootstrap(Request $r, VtuServiceRegistry $rService): JsonResponse|\Illuminate\Http\RedirectResponse{try{$rService->bootstrapCatalogue();$message='VTU service registry synchronized.';return $r->expectsJson()?response()->json(['status'=>'completed','message'=>$message]):back()->with('success',$message);}catch(\Throwable $e){report($e);$message='VTU service registry synchronization failed safely.';return $r->expectsJson()?response()->json(['message'=>$message],500):back()->with('error',$message);}}
}
