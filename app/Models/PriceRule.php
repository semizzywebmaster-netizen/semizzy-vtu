<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;

class PriceRule extends Model
{
 protected $fillable=['scope_type','scope_id','customer_tier','rule_type','fixed_fee','percentage','minimum_price','maximum_price','rounding_increment','enabled','effective_from','effective_to','priority','created_by'];
 protected function casts():array{return ['fixed_fee'=>'decimal:6','percentage'=>'decimal:6','minimum_price'=>'decimal:6','maximum_price'=>'decimal:6','enabled'=>'boolean','effective_from'=>'datetime','effective_to'=>'datetime'];}
 public function creator():BelongsTo{return $this->belongsTo(User::class,'created_by');}
}
