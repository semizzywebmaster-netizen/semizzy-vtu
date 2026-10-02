<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;

class ProviderServiceProduct extends Model
{
 protected $fillable=['api_provider_id','service_product_id','provider_product_id','provider_cost','currency','raw_catalogue','enabled','last_synced_at'];
 protected function casts():array{return ['provider_cost'=>'decimal:6','raw_catalogue'=>'array','enabled'=>'boolean','last_synced_at'=>'datetime'];}
 public function provider():BelongsTo{return $this->belongsTo(ApiProvider::class,'api_provider_id');}
 public function product():BelongsTo{return $this->belongsTo(ServiceProduct::class,'service_product_id');}
}
