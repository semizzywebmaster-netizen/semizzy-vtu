<?php
namespace Addons\InsuranceProtection\Services;
use Addons\InsuranceProtection\Models\{InsuranceProduct,InsurancePolicy,InsuranceClaim};
use App\Models\User;
use App\Services\Providers\ProviderManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
class InsuranceService {
 public function __construct(private InsuranceWalletService $wallet,private ProviderManager $providers){}
 public function purchase(User $user,InsuranceProduct $product,array $holder,string $key): InsurancePolicy {
  $existing=InsurancePolicy::where('idempotency_key',$key)->where('user_id',$user->id)->first();
  if($existing)return $existing;
  if(!$product->active)throw new RuntimeException('Insurance product is unavailable.');
  if(InsurancePolicy::where('user_id',$user->id)->where('insurance_product_id',$product->id)->whereIn('status',['pending','active','issued'])->exists())throw new RuntimeException('You already have an active policy for this product.');
  $p=InsurancePolicy::create(['user_id'=>$user->id,'insurance_product_id'=>$product->id,'insurance_provider_id'=>$product->insurance_provider_id,'reference'=>'INS-'.strtoupper(Str::random(18)),'idempotency_key'=>$key,'status'=>'pending','currency'=>$product->currency,'premium_minor'=>$product->premium_minor,'coverage_minor'=>$product->coverage['amount_minor']??null,'holder_snapshot'=>$holder,'product_snapshot'=>$product->toArray(),'provider_snapshot'=>$product->provider?->only(['id','name','driver'])]);
  try{$this->wallet->reserve($p);$result=$this->providers->execute('insurance','insurance_purchase',['product_code'=>$product->code,'holder'=>$holder,'premium_minor'=>$p->premium_minor],$key);if($result->accepted){$p->update(['status'=>'active','provider_reference'=>$result->providerReference,'issued_at'=>now(),'starts_at'=>now(),'expires_at'=>now()->addYear()]);$this->wallet->settle($p);return $p->fresh();}if($result->duplicateRisk||in_array(strtoupper($result->status),['UNKNOWN','PENDING','PROCESSING'],true)){$p->update(['status'=>'provider_pending','provider_reference'=>$result->providerReference]);return $p->fresh();}throw new RuntimeException($result->message?:'Insurance provider rejected the purchase.');}catch(\Throwable $e){if($p->status==='pending'){try{$this->wallet->release($p);}catch(\Throwable $ignored){}}$p->update(['status'=>'failed']);throw $e;}
 }
 public function requery(User $user,InsurancePolicy $p): InsurancePolicy {
  if($p->user_id!==$user->id) throw new RuntimeException('Policy not found.');
  if(!in_array($p->status,['provider_pending','pending'],true)) return $p;
  $result=$this->providers->execute('insurance','insurance_policy_status',['provider_reference'=>$p->provider_reference,'policy_reference'=>$p->reference],$p->idempotency_key.':requery');
  $state=strtoupper((string)$result->status);
  if($result->accepted||in_array($state,['SUCCESS','ACTIVE','ISSUED','COMPLETED'],true)){ $p->update(['status'=>'active','provider_status'=>$result->status,'issued_at'=>$p->issued_at?:now(),'starts_at'=>$p->starts_at?:now(),'expires_at'=>$p->expires_at?:now()->addYear()]); try{$this->wallet->settle($p);}catch(\Throwable $e){} return $p->fresh(); }
  if(in_array($state,['FAILED','REJECTED','CANCELLED'],true)){ $p->update(['status'=>'failed','provider_status'=>$result->status]); try{$this->wallet->release($p);}catch(\Throwable $e){} }
  else $p->update(['status'=>'provider_pending','provider_status'=>$result->status,'provider_reference'=>$result->providerReference?:$p->provider_reference]);
  return $p->fresh();
 }
 public function cancel(User $user,InsurancePolicy $p,string $reason): InsurancePolicy {
  if($p->user_id!==$user->id) throw new RuntimeException('Policy not found.');
  if(!in_array($p->status,['active','issued'],true)) throw new RuntimeException('Only active policies can be cancelled.');
  $p->update(['status'=>'cancelled','cancel_reason'=>$reason,'cancelled_at'=>now()]);
  return $p->fresh();
 }
 public function renew(User $user,InsurancePolicy $p,string $key): InsurancePolicy {
  if($p->user_id!==$user->id) throw new RuntimeException('Policy not found.');
  if(!in_array($p->status,['active','issued'],true)) throw new RuntimeException('Policy is not eligible for renewal.');
  $this->wallet->reserveRenewal($p,$key);
  try {
   $result=$this->providers->execute('insurance','insurance_renew',['provider_reference'=>$p->provider_reference,'policy_reference'=>$p->reference,'premium_minor'=>$p->premium_minor],$key);
   if($result->accepted){$p->update(['status'=>'active','provider_reference'=>$result->providerReference?:$p->provider_reference,'renewed_at'=>now(),'renewal_due_at'=>now()->addYear(),'expires_at'=>now()->addYear()]);$this->wallet->settleRenewal($p,$key);return $p->fresh();}
   if($result->duplicateRisk||in_array(strtoupper((string)$result->status),['UNKNOWN','PENDING','PROCESSING'],true)){$p->update(['status'=>'provider_pending','provider_status'=>$result->status,'provider_reference'=>$result->providerReference?:$p->provider_reference]);return $p->fresh();}
   $this->wallet->releaseRenewal($p,$key);
   throw new RuntimeException($result->message?:'Insurance renewal was rejected.');
  } catch(\Throwable $e) { if($p->status!=='provider_pending'){try{$this->wallet->releaseRenewal($p,$key);}catch(\Throwable $ignored){}} throw $e; }
 }
 public function claim(User $user,InsurancePolicy $policy,array $data,string $key): InsuranceClaim { if($policy->user_id!==$user->id||!in_array($policy->status,['active','issued'],true)) throw new RuntimeException('Policy is not eligible for a claim.'); $existing=InsuranceClaim::where('idempotency_key',$key)->where('user_id',$user->id)->first(); if($existing) return $existing; $claim=InsuranceClaim::create(['insurance_policy_id'=>$policy->id,'user_id'=>$user->id,'reference'=>'CLM-'.strtoupper(Str::random(18)),'idempotency_key'=>$key,'status'=>'submitted','claim_type'=>$data['claim_type']??null,'amount_minor'=>$data['amount_minor']??null,'description'=>$data['description'],'documents'=>$data['documents']??[],'submitted_at'=>now()]); try { $result=$this->providers->execute('insurance','insurance_claim',['policy_reference'=>$policy->reference,'provider_reference'=>$policy->provider_reference,'claim_reference'=>$claim->reference,'claim_type'=>$claim->claim_type,'amount_minor'=>$claim->amount_minor,'description'=>$claim->description,'documents'=>$claim->documents],$key); $claim->update(['provider_status'=>$result->status,'provider_reference'=>$result->providerReference,'submitted_to_provider_at'=>now()]); if($result->accepted) $claim->update(['status'=>'under_review']); elseif($result->duplicateRisk||in_array(strtoupper((string)$result->status),['UNKNOWN','PENDING','PROCESSING'],true)) $claim->update(['status'=>'submitted']); else $claim->update(['status'=>'rejected']); } catch(\Throwable $e) { $claim->update(['provider_status'=>'PENDING']); } return $claim->fresh(); }
}