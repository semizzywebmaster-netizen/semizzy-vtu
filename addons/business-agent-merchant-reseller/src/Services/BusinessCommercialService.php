<?php
namespace Addons\BusinessAgentMerchantReseller\Services;
use Addons\BusinessAgentMerchantReseller\Models\BusinessPartner;
use Illuminate\Support\Facades\DB;
use RuntimeException;
class BusinessCommercialService{
 public function assertCanTransact(BusinessPartner $p,int $amountMinor):void{
  if($p->status!=='active')throw new RuntimeException('Business partner is not active.');
  if($amountMinor<0)throw new RuntimeException('Invalid transaction amount.');
  if($p->minimum_balance_minor>0){$balance=(int)($p->user?->wallet?->available_balance_minor??0);if($balance<$p->minimum_balance_minor)throw new RuntimeException('Minimum balance requirement not met.');}
  $c=DB::table('business_limit_counters')->where('business_partner_id',$p->id)->where('period_date',now()->toDateString())->lockForUpdate()->first();
  $daily=$c?->daily_used_minor??0;$monthly=$c?->monthly_used_minor??0;
  if($p->daily_limit_minor!==null && $daily+$amountMinor>$p->daily_limit_minor)throw new RuntimeException('Daily business transaction limit exceeded.');
  if($p->monthly_limit_minor!==null && $monthly+$amountMinor>$p->monthly_limit_minor)throw new RuntimeException('Monthly business transaction limit exceeded.');
 }
 public function record(BusinessPartner $p,int $amountMinor):void{
  DB::table('business_limit_counters')->upsert([['business_partner_id'=>$p->id,'period_date'=>now()->toDateString(),'period_month'=>now()->startOfMonth()->toDateString(),'daily_used_minor'=>$amountMinor,'monthly_used_minor'=>$amountMinor,'created_at'=>now(),'updated_at'=>now()]],['business_partner_id','period_date'],['updated_at']);
  DB::table('business_limit_counters')->where('business_partner_id',$p->id)->where('period_date',now()->toDateString())->update(['daily_used_minor'=>DB::raw('daily_used_minor + '.(int)$amountMinor),'monthly_used_minor'=>DB::raw('monthly_used_minor + '.(int)$amountMinor),'updated_at'=>now()]);
 }
 public function pricing(BusinessPartner $p,string $serviceKey,?string $productKey=null):?object{
  return DB::table('business_pricing_rules')->where('business_partner_id',$p->id)->where('service_key',$serviceKey)->where('enabled',true)->where(function($q)use($productKey){$q->whereNull('product_key');if($productKey)$q->orWhere('product_key',$productKey);})->orderByRaw('CASE WHEN product_key IS NULL THEN 1 ELSE 0 END')->first();
 }
}
