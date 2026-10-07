<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\Social\Models\SocialAccount;
use Semizzy\Addons\Social\Models\SocialVerificationRequest;
Route::middleware(['web','auth','ensure.addon:social.accounts-verification'])->prefix('/admin/social')->group(function(){
 Route::get('/',fn()=>inertia('Admin/SocialAccounts',['accounts'=>SocialAccount::with('user')->latest()->paginate(50),'requests'=>SocialVerificationRequest::with('socialAccount','user')->latest()->paginate(50)])->middleware('permission:social.view'));
 Route::post('/requests/{request}/approve',function(SocialVerificationRequest $request){$request->status='verified';$request->verified_at=now();$request->save();$request->socialAccount()->update(['verification_status'=>'verified','verification_method'=>'manual']);return back();})->middleware(['permission:social.verification.manage','throttle:30,1']);
});