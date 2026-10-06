<?php
namespace Semizzy\\Addons\\Savings\\Models;
use Illuminate\\Database\\Eloquent\\Model;
class SavingsAccount extends Model { protected $table='savings_accounts'; protected $guarded=[]; protected $casts=['target_amount_minor'=>'integer','balance_minor'=>'integer','matures_at'=>'datetime','last_contribution_at'=>'datetime','metadata'=>'array']; public function plan(){return $this->belongsTo(SavingsPlan::class,'savings_plan_id');} }