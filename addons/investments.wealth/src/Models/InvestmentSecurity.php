<?php
namespace Semizzy\Addons\Investments\Models;
use Illuminate\Database\Eloquent\Model;
class InvestmentSecurity extends Model {
 protected $table='investment_securities';
 protected $guarded=[];
 protected $casts=['metadata'=>'array','published_at'=>'datetime','source_checked_at'=>'datetime','verified'=>'boolean'];
 public function quotes(){return $this->hasMany(InvestmentMarketQuote::class,'security_id');}
 public function holdings(){return $this->hasMany(InvestmentHolding::class,'security_id');}
}