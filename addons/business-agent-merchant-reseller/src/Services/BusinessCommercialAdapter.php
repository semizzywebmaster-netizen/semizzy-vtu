<?php
namespace Addons\BusinessAgentMerchantReseller\Services;

use App\Contracts\CommercialServiceAdapter;
use App\Models\User;
use Addons\BusinessAgentMerchantReseller\Models\BusinessPartner;

class BusinessCommercialAdapter implements CommercialServiceAdapter
{
 public function __construct(private BusinessCommercialService $commercial) {}

 public function serviceKey(): string { return 'business-commercial'; }

 public function canHandle(string $serviceKey, ?string $productKey = null): bool { return true; }

 public function quote(int $userId,string $serviceKey,?string $productKey,int $baseAmountMinor): array
 {
  $partner=$this->partner($userId);
  if(!$partner) return ['amount_minor'=>$baseAmountMinor,'partner_id'=>null,'rule_id'=>null];
  $rule=$this->commercial->pricing($partner,$serviceKey,$productKey);
  return ['amount_minor'=>$this->commercial->applyPricing($baseAmountMinor,$rule),'partner_id'=>$partner->id,'rule_id'=>$rule?->id];
 }

 public function authorize(int $userId,string $serviceKey,?string $productKey,int $amountMinor): void
 {
  $partner=$this->partner($userId);
  if($partner) $this->commercial->assertCanTransact($partner,$amountMinor);
 }

 public function record(int $userId,string $serviceKey,?string $productKey,int $amountMinor,string $transactionKey): void
 {
  $partner=$this->partner($userId);
  if($partner) $this->commercial->record($partner,$amountMinor,$serviceKey,$productKey,$transactionKey);
 }

 private function partner(int $userId): ?BusinessPartner
 {
  $user=User::query()->find($userId);
  if(!$user || !in_array(strtoupper((string)$user->role),['AGENT','MERCHANT','RESELLER'],true)) return null;
  return BusinessPartner::query()
   ->where('user_id',$userId)
   ->where('type',strtolower((string)$user->role))
   ->where('status','active')
   ->latest('id')->first();
 }
}
