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
 public function claim(User $user,InsurancePolicy $policy,array $data): InsuranceClaim {if($policy->user_id!==$user->id||!in_array($policy->status,['active','issued'],true))throw new RuntimeException('Policy is not eligible for a claim.');return InsuranceClaim::create(['insurance_policy_id'=>$policy->id,'user_id'=>$user->id,'reference'=>'CLM-'.strtoupper(Str::random(18)),'status'=>'submitted','claim_type'=>$data['claim_type']??null,'amount_minor'=>$data['amount_minor']??null,'description'=>$data['description'],'documents'=>$data['documents']??[],'submitted_at'=>now()]);}
}