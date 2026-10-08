<?php
namespace Semizzy\Addons\Investments\Models;
use Illuminate\Database\Eloquent\Model;
class InvestmentMarketQuote extends Model {
 protected $table='investment_market_quotes'; protected $guarded=[];
 protected $casts=['observed_at'=>'datetime','expires_at'=>'datetime','raw_metadata'=>'array'];
 public function security(){return $this->belongsTo(InvestmentSecurity::class,'security_id');}
}