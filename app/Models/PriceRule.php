<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceRule extends Model
{
 protected $fillable=['scope_type','scope_id','customer_tier','rule_type','fixed_fee','percentage','minimum_price','maximum_price','rounding_increment','enabled','effective_from','effective_to','priority','created_by'];
 protected function casts():array{return ['fixed_fee'=>'decimal:6','percentage'=>'decimal:6','minimum_price'=>'decimal:6','maximum_price'=>'decimal:6','enabled'=>'boolean','effective_from'=>'datetime','effective_to'=>'datetime'];}
 protected static function booted(): void
 {
  static::saving(function (PriceRule $rule): void {
   $rule->scope_type = strtoupper(trim($rule->scope_type));
   $rule->rule_type = strtolower(trim($rule->rule_type));
   if ($rule->customer_tier !== null) {
    $rule->customer_tier = strtoupper(trim($rule->customer_tier));
   }
   $validScopes = ['GLOBAL', 'CATEGORY', 'SERVICE', 'PRODUCT'];
   $validTypes = ['fixed', 'percentage', 'fixed_percentage'];
   $validTiers = ['USER', 'AGENT', 'RESELLER', 'MERCHANT', 'CUSTOM'];
   if (! in_array($rule->scope_type, $validScopes, true) || ($rule->scope_type === 'GLOBAL' && $rule->scope_id !== null)) {
    throw new InvalidArgumentException('Invalid pricing scope.');
   }
   if (! in_array($rule->rule_type, $validTypes, true)) {
    throw new InvalidArgumentException('Invalid pricing rule type.');
   }
   if ($rule->customer_tier !== null && ! in_array($rule->customer_tier, $validTiers, true)) {
    throw new InvalidArgumentException('Invalid customer tier.');
   }
   if ($rule->minimum_price !== null && $rule->maximum_price !== null && BigDecimal::of((string) $rule->minimum_price)->isGreaterThan(BigDecimal::of((string) $rule->maximum_price))) {
    throw new InvalidArgumentException('Minimum price cannot exceed maximum price.');
   }
   if ((int) $rule->rounding_increment < 0) {
    throw new InvalidArgumentException('Rounding increment cannot be negative.');
   }
  });
 }

 public function creator():BelongsTo{return $this->belongsTo(User::class,'created_by');}
}
