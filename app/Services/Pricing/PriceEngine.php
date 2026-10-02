<?php

namespace App\Services\Pricing;

use App\Models\PriceRule;
use App\Models\ServiceProduct;
use App\Models\ApiProvider;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;

class PriceEngine
{
 public function quote(ServiceProduct $product,string $customerTier='USER',?string $at=null,?ApiProvider $provider=null):array
 {
  $cost=BigDecimal::of($this->resolveProviderCost($product,$provider));
  $rules=$this->rules($product,$customerTier,$at);
  $rule=$rules->first();
  $price=$cost;
  if($rule){
   $percentage=BigDecimal::of((string)$rule->percentage);
   $fixed=BigDecimal::of((string)$rule->fixed_fee);
   if($rule->rule_type==='fixed') $price=$cost->plus($fixed);
   elseif($rule->rule_type==='percentage') $price=$cost->plus($cost->multipliedBy($percentage)->dividedBy(100,12,RoundingMode::HALF_UP));
   else $price=$cost->plus($cost->multipliedBy($percentage)->dividedBy(100,12,RoundingMode::HALF_UP))->plus($fixed);
   if($rule->minimum_price!==null && $price->isLessThan(BigDecimal::of((string)$rule->minimum_price))) $price=BigDecimal::of((string)$rule->minimum_price);
   if($rule->maximum_price!==null && $price->isGreaterThan(BigDecimal::of((string)$rule->maximum_price))) $price=BigDecimal::of((string)$rule->maximum_price);
   if($rule->rounding_increment>0){ $increment=BigDecimal::of((string)$rule->rounding_increment)->dividedBy(100,6,RoundingMode::HALF_UP); $price=$price->dividedBy($increment,0,RoundingMode::HALF_UP)->multipliedBy($increment); }
  }
  return ['provider_cost'=>(string)$cost->toScale(6,RoundingMode::HALF_UP),'customer_price'=>(string)$price->toScale(2,RoundingMode::HALF_UP),'rule_id'=>$rule?->id,'currency'=>$product->currency];
 }

 private function rules(ServiceProduct $product,string $tier,?string $at):\Illuminate\Database\Eloquent\Collection
 {
  $time=$at?Carbon::parse($at):now();
  return PriceRule::query()->where('enabled',true)->where(function($q)use($product){$q->where(fn($x)=>$x->where('scope_type','PRODUCT')->where('scope_id',$product->id))->orWhere(fn($x)=>$x->where('scope_type','SERVICE')->where('scope_id',$product->service_id))->orWhere(fn($x)=>$x->where('scope_type','CATEGORY')->where('scope_id',$product->service->category_id))->orWhere('scope_type','GLOBAL');})
   ->where(function($q)use($tier){$q->whereNull('customer_tier')->orWhere('customer_tier',$tier);})
   ->where(function($q)use($time){$q->whereNull('effective_from')->orWhere('effective_from','<=',$time);})
   ->where(function($q)use($time){$q->whereNull('effective_to')->orWhere('effective_to','>=',$time);})
   ->orderByRaw("CASE scope_type WHEN 'PRODUCT' THEN 1 WHEN 'SERVICE' THEN 2 WHEN 'CATEGORY' THEN 3 ELSE 4 END")->orderByRaw("CASE WHEN customer_tier IS NULL THEN 2 ELSE 1 END")->orderBy('priority')->get();
 }

 private function resolveProviderCost(ServiceProduct $product, ?ApiProvider $provider): string
 {
  $query=$product->providerProducts()->where('enabled',true)->whereNotNull('provider_cost');
  if($provider) $query->where('api_provider_id',$provider->id);
  $cost=$query->orderBy('provider_cost')->value('provider_cost');
  if($cost!==null) return (string)$cost;
  if($product->provider_cost!==null) return (string)$product->provider_cost;
  return '0';
 }
}
