<?php
namespace Semizzy\Addons\Investments\Models;
use Illuminate\Database\Eloquent\Model;
class InvestmentHolding extends Model {
 protected $table='investment_holdings'; protected $guarded=[];
 protected $casts=['quantity'=>'decimal:8','average_cost'=>'decimal:8','cost_basis'=>'decimal:8','last_reconciled_at'=>'datetime','metadata'=>'array'];
 public function security(){return $this->belongsTo(InvestmentSecurity::class,'security_id');}
}