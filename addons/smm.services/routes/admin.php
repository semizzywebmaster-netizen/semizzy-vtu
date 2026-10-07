<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Smm\Models\SmmService;
use Semizzy\Addons\Smm\Models\SmmServiceCategory;

Route::middleware(['web','auth','ensure.addon:smm.services'])->prefix('/admin/smm')->group(function(){
 Route::get('/',function(){return inertia('Admin/SMM/Index',['services'=>SmmService::with('category')->orderBy('platform')->orderBy('name')->paginate(50),'categories'=>SmmServiceCategory::where('active',true)->orderBy('name')->get()]);})->middleware('permission:smm.view')->name('admin.smm.index');
 Route::post('/services',function(Request $r){
  $d=$r->validate(['category_id'=>['required','integer','exists:smm_service_categories,id'],'service_key'=>['required','string','max:120','regex:/^[a-z0-9._-]+$/'],'name'=>['required','string','max:160'],'platform'=>['required','string','max:80'],'operation'=>['required','string','max:80'],'mode'=>['required','in:provider_api,manual'],'min_quantity'=>['required','integer','min:1'],'max_quantity'=>['required','integer','gte:min_quantity'],'unit_price_minor'=>['required','integer','min:0'],'currency'=>['required','string','size:3'],'active'=>['boolean'],'requirements'=>['nullable','array'],'metadata'=>['nullable','array']]);
  $service=SmmService::create($d); return response()->json(['data'=>$service],201);
 })->middleware(['permission:smm.services.manage','throttle:20,1']);
 Route::patch('/services/{service}',function(Request $r,SmmService $service){
  $d=$r->validate(['category_id'=>['sometimes','integer','exists:smm_service_categories,id'],'name'=>['sometimes','string','max:160'],'platform'=>['sometimes','string','max:80'],'operation'=>['sometimes','string','max:80'],'mode'=>['sometimes','in:provider_api,manual'],'min_quantity'=>['sometimes','integer','min:1'],'max_quantity'=>['sometimes','integer','gte:min_quantity'],'unit_price_minor'=>['sometimes','integer','min:0'],'currency'=>['sometimes','string','size:3'],'active'=>['sometimes','boolean'],'requirements'=>['nullable','array'],'metadata'=>['nullable','array']]);
  $service->fill($d); if($service->max_quantity<$service->min_quantity) abort(422,'max_quantity must be >= min_quantity.'); $service->save(); return response()->json(['data'=>$service->fresh('category')]);
 })->middleware(['permission:smm.services.manage','throttle:30,1']);
 Route::post('/services/{service}/toggle',function(SmmService $service){$service->active=!$service->active;$service->save();return response()->json(['data'=>$service->fresh()]);})->middleware(['permission:smm.services.manage','throttle:30,1']);
});