<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;
use Illuminate\\Database\\Eloquent\\Relations\\HasMany;

class ServiceProduct extends Model
{
 protected $fillable=['service_id','key','name','provider_product_id','provider_cost','currency','metadata','enabled'];
 protected function casts():array{return ['provider_cost'=>'decimal:6','metadata'=>'array','enabled'=>'boolean'];}
 public function service():BelongsTo{return $this->belongsTo(Service::class);}
 public function providerProducts():HasMany{return $this->hasMany(ProviderServiceProduct::class);}
}
