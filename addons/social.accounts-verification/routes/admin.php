<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Semizzy\Addons\Social\Models\SocialAccount;
use Semizzy\Addons\Social\Models\SocialVerificationRequest;
use Semizzy\Addons\Social\Models\SocialPlatform;
use Semizzy\Addons\Social\Models\SocialVerificationMethod;
use Semizzy\Addons\Social\Models\SocialProviderMapping;
use App\Models\ApiProvider;
use Semizzy\Addons\Social\Services\SocialVerificationService;
Route::middleware(['web','auth','ensure.addon:social.accounts-verification'])->prefix('/admin/social')->group(function(){
 Route::get('/',fn()=>inertia('Admin/SocialAccounts',['accounts'=>SocialAccount::with('user')->latest()->paginate(50),'requests'=>SocialVerificationRequest::with('socialAccount','user')->latest()->paginate(50)])->middleware('permission:social.view'));
 Route::get('/catalogue',function(){return response()->json(['platforms'=>SocialPlatform::where('active',true)->orderBy('name')->get(),'methods'=>SocialVerificationMethod::where('active',true)->orderBy('name')->get(),'providers'=>ApiProvider::query()->select(['id','display_name','identifier','enabled','paused','verification_status','integration_status'])->orderBy('display_name')->get(),'mappings'=>SocialProviderMapping::with(['platform','provider'])->get()]);})->middleware('permission:social.verification.manage');
 Route::post('/platforms',function(\Illuminate\Http\Request $r){$d=$r->validate(['platform_key'=>['required','string','max:80','regex:/^[a-z0-9._-]+$/'],'name'=>['required','string','max:120'],'verification_mode'=>['required','in:manual,provider_or_manual,provider'],'requirements'=>['nullable','array'],'metadata'=>['nullable','array']]);return response()->json(['data'=>SocialPlatform::create($d)],201);})->middleware(['permission:social.verification.manage','throttle:20,1']);
 Route::post('/mappings',function(\Illuminate\Http\Request $r){$d=$r->validate(['social_platform_id'=>['required','exists:social_platforms,id'],'api_provider_id'=>['required','exists:api_providers,id'],'capability'=>['required','in:social_account_verify,social_account_status,social_account_profile'],'provider_service_id'=>['nullable','string','max:160'],'enabled'=>['boolean']]);$m=SocialProviderMapping::updateOrCreate(['social_platform_id'=>$d['social_platform_id'],'api_provider_id'=>$d['api_provider_id'],'capability'=>$d['capability']],$d);return response()->json(['data'=>$m->load(['platform','provider'])],201);})->middleware(['permission:social.verification.manage','throttle:30,1']);
 Route::post('/requests/{request}/approve',function(SocialVerificationRequest $request,SocialVerificationService $service){$service->approveManual($request);return back();})->middleware(['permission:social.verification.manage','throttle:30,1']);
});