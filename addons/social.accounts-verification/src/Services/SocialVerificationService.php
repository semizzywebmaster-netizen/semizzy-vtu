<?php
namespace Semizzy\Addons\Social\Services;

use App\Services\Providers\ProviderManager;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Semizzy\Addons\Social\Models\SocialAccount;
use Semizzy\Addons\Social\Models\SocialVerificationRequest;

final class SocialVerificationService
{
 public function __construct(private ProviderManager $providers) {}

 public function request(SocialAccount $account,int $userId,string $method='manual',?string $evidence=null): SocialVerificationRequest
 {
  if((int)$account->user_id!==$userId) throw new RuntimeException('Account ownership mismatch.');
  if($account->verification_status==='verified') return $account->verificationRequests()->latest()->firstOrFail();
  return DB::transaction(function() use($account,$userId,$method,$evidence){
   $account->refresh();
   $request=SocialVerificationRequest::create([
    'social_account_id'=>$account->id,'user_id'=>$userId,'reference'=>'SOC-'.Str::upper(Str::random(20)),
    'method'=>$method,'status'=>'pending','evidence'=>$evidence,'expires_at'=>now()->addHours(24)
   ]);
   $account->update(['verification_status'=>'pending','verification_method'=>$method]);
   return $request;
  });
 }

 public function verify(SocialVerificationRequest $request): SocialVerificationRequest
 {
  $request->loadMissing('socialAccount');
  if($request->status!=='pending') return $request;
  if($request->expires_at && $request->expires_at->isPast()){return $this->mark($request,'expired');}
  $account=$request->socialAccount;
  if($request->method==='manual') return $request->fresh();
  $result=$this->providers->execute($account->platform,'social_account_verify',[
   'username'=>$account->username,'account_reference'=>$account->account_reference,'reference'=>$request->reference
  ],$request->reference);
  $status=strtoupper((string)$result->status);
  $request->provider_reference=$result->providerReference ?: $request->provider_reference;
  $request->metadata=array_merge((array)$request->metadata,['provider_status'=>$status,'message'=>$result->message]);
  if($result->accepted || in_array($status,['VERIFIED','SUCCESS','SUCCESSFUL','COMPLETED'],true)){
   $request->status='verified';$request->verified_at=now();$account->update(['verification_status'=>'verified','provider_reference'=>$request->provider_reference]);
  }elseif(in_array($status,['FAILED','REJECTED','INVALID'],true)){
   $request->status='failed';$account->update(['verification_status'=>'failed']);
  }else{$request->status='processing';$account->update(['verification_status'=>'processing']);}
  $request->save();
  return $request->fresh('socialAccount');
 }

 public function requery(SocialVerificationRequest $request): SocialVerificationRequest
 {
  $request->loadMissing('socialAccount');
  if(!in_array($request->status,['pending','processing'],true)) return $request;
  if($request->expires_at && $request->expires_at->isPast()) return $this->mark($request,'expired');
  if(!$request->provider_reference) return $this->verify($request);
  $result=$this->providers->execute($request->socialAccount->platform,'social_account_status',['provider_reference'=>$request->provider_reference,'reference'=>$request->reference],$request->reference.':requery');
  $status=strtoupper((string)$result->status);
  if(in_array($status,['VERIFIED','SUCCESS','SUCCESSFUL','COMPLETED'],true)||$result->accepted){$request->status='verified';$request->verified_at=now();$request->socialAccount->update(['verification_status'=>'verified']);}
  elseif(in_array($status,['FAILED','REJECTED','INVALID'],true)){$request->status='failed';$request->socialAccount->update(['verification_status'=>'failed']);}
  else{$request->status='processing';}
  $request->provider_reference=$result->providerReference ?: $request->provider_reference;
  $request->metadata=array_merge((array)$request->metadata,['requery_status'=>$status,'message'=>$result->message]);$request->save();
  return $request->fresh('socialAccount');
 }

 public function approveManual(SocialVerificationRequest $request): SocialVerificationRequest
 {
  if($request->status!=='pending') return $request;
  return DB::transaction(function() use($request){
   $request->status='verified';$request->verified_at=now();$request->save();
   $request->socialAccount()->update(['verification_status'=>'verified','verification_method'=>'manual']);
   return $request->fresh('socialAccount');
  });
 }

 private function mark(SocialVerificationRequest $request,string $status): SocialVerificationRequest
 {
  $request->status=$status;$request->save();
  if($status==='expired')$request->socialAccount()->update(['verification_status'=>'expired']);
  return $request->fresh('socialAccount');
 }
}