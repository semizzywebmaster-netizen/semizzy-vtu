<?php
namespace Addons\BusinessAgentMerchantReseller\Services;

use Addons\BusinessAgentMerchantReseller\Models\BusinessCommissionSettlement;
use Addons\BusinessAgentMerchantReseller\Models\BusinessPartner;
use App\Models\WalletAccount;
use App\Models\WalletMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class BusinessCommissionService
{
 public function accrue(BusinessPartner $source,string $serviceKey,?string $productKey,int $baseAmountMinor,string $transactionKey,string $currency='NGN'): array
 {
  if($baseAmountMinor<0 || trim($transactionKey)==='') throw new RuntimeException('Invalid commission source transaction.');
  $created=[];
  $seen=[$source->id=>true]; $current=$source; $remainingBps=10000; $depth=0;

  while($current->parent_partner_id!==null && $depth<10 && $remainingBps>0){
   $parent=BusinessPartner::query()->whereKey($current->parent_partner_id)->where('status','active')->first();
   if(!$parent || isset($seen[$parent->id])) break;
   $seen[$parent->id]=true; $depth++;

   $rate=max(0,min($remainingBps,(int)$parent->commission_rate_bps));
   $amount=$this->percentage($baseAmountMinor,$rate);
   $remainingBps-=$rate;

   if($rate>0 && $amount>0){
    $row=BusinessCommissionSettlement::query()->firstOrCreate(
      ['transaction_key'=>$transactionKey,'beneficiary_partner_id'=>$parent->id],
      ['business_partner_id'=>$source->id,'service_key'=>$serviceKey,'product_key'=>$productKey,'base_amount_minor'=>$baseAmountMinor,'rate_bps'=>$rate,'amount_minor'=>$amount,'depth'=>$depth,'currency'=>strtoupper($currency),'status'=>'pending','metadata'=>['source_partner_id'=>$source->id]]
    );
    $created[]=$row;
   }
   $current=$parent;
  }
  return $created;
 }

 public function settle(int $settlementId): BusinessCommissionSettlement
 {
  return DB::transaction(function() use($settlementId){
   $claim=BusinessCommissionSettlement::query()->lockForUpdate()->findOrFail($settlementId);
   if($claim->status==='settled') return $claim;
   if($claim->status!=='pending') throw new RuntimeException('Commission settlement is not payable in its current state.');

   $partner=BusinessPartner::query()->whereKey($claim->beneficiary_partner_id)->where('status','active')->first();
   if(!$partner) throw new RuntimeException('Commission beneficiary is not active.');

   $wallet=WalletAccount::query()->where('user_id',$partner->user_id)->lockForUpdate()->first();
   if(!$wallet || $wallet->status!=='active') throw new RuntimeException('Commission beneficiary wallet is unavailable.');
   if(strtoupper((string)$wallet->currency)!==strtoupper((string)$claim->currency)) throw new RuntimeException('Commission currency does not match beneficiary wallet.');

   $before=(string)$wallet->available_minor; $amount=(string)$claim->amount_minor;
   $after=$this->add($before,$amount);
   $reference='BIZ-COMM-'.$claim->id.'-'.Str::upper(Str::random(10));
   $wallet->available_minor=$after; $wallet->saveOrFail();

   WalletMovement::create([
    'wallet_account_id'=>$wallet->id,'operation_key'=>'business:commission:'.$claim->id,
    'reference'=>$reference,'type'=>'business_commission','amount_minor'=>$amount,'currency'=>$wallet->currency,
    'available_before_minor'=>$before,'available_after_minor'=>$after,
    'held_before_minor'=>(string)$wallet->held_minor,'held_after_minor'=>(string)$wallet->held_minor,
    'metadata'=>['settlement_id'=>$claim->id,'source_partner_id'=>$claim->business_partner_id,'transaction_key'=>$claim->transaction_key,'service_key'=>$claim->service_key,'depth'=>$claim->depth]
   ]);

   $claim->status='settled'; $claim->settlement_reference=$reference; $claim->settled_at=now(); $claim->saveOrFail();
   return $claim->fresh();
  });
 }

 private function percentage(int $base,int $rate): int {
  if ($base < 0 || $rate < 0 || $rate > 10000) throw new RuntimeException('Invalid commission calculation.');
  if ($rate === 0 || $base === 0) return 0;
  if ($base > intdiv(PHP_INT_MAX - 5000, $rate)) {
   if (!function_exists('bcmul')) throw new RuntimeException('Large commission calculations require BCMath.');
   return (int) bcdiv(bcadd(bcmul((string)$base,(string)$rate,0),'5000',0),'10000',0);
  }
  return intdiv($base * $rate + 5000,10000);
 }

 private function add(string $a,string $b):string {
  if(function_exists('bcadd')) return bcadd($a,$b,0);
  $a=ltrim($a,'0')?:'0'; $b=ltrim($b,'0')?:'0';
  if(strlen($a)>17||strlen($b)>17) throw new RuntimeException('Large commission balances require BCMath.');
  return (string)((int)$a+(int)$b);
 }
}