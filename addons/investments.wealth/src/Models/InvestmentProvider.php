<?php
namespace Semizzy\Addons\Investments\Models;
use Illuminate\Database\Eloquent\Model;
class InvestmentProvider extends Model {
 protected $table='investment_providers'; protected $guarded=[];
 protected $casts=['capabilities'=>'array','settings'=>'array','verified'=>'boolean','is_data_provider'=>'boolean','is_execution_provider'=>'boolean'];
 public function orders(){return $this->hasMany(InvestmentOrder::class,'provider_id');}
}