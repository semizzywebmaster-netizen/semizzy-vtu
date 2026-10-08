<?php
namespace Semizzy\Addons\Investments\Models;
use Illuminate\Database\Eloquent\Model;
class InvestmentExecution extends Model {
 protected $table='investment_executions'; protected $guarded=[];
 protected $casts=['quantity'=>'decimal:8','price'=>'decimal:8','gross_amount'=>'decimal:8','fees'=>'decimal:8','executed_at'=>'datetime','metadata'=>'array'];
 public function order(){return $this->belongsTo(InvestmentOrder::class,'investment_order_id');}
}