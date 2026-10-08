<?php
namespace Semizzy\Addons\Investments\Models;
use Illuminate\Database\Eloquent\Model;
class InvestmentOrder extends Model {
 protected $table='investment_orders'; protected $guarded=[];
 protected $casts=['quantity'=>'decimal:8','limit_price'=>'decimal:8','estimated_amount'=>'decimal:8','submitted_at'=>'datetime','completed_at'=>'datetime','metadata'=>'array'];
 public function security(){return $this->belongsTo(InvestmentSecurity::class,'security_id');}
 public function provider(){return $this->belongsTo(InvestmentProvider::class,'provider_id');}
 public function executions(){return $this->hasMany(InvestmentExecution::class,'investment_order_id');}
}