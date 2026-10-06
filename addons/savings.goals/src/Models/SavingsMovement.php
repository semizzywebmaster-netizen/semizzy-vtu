<?php
namespace Semizzy\Addons\Savings\Models;
use Illuminate\Database\Eloquent\Model;
class SavingsMovement extends Model { protected $table='savings_movements'; protected $guarded=[]; protected $casts=['amount_minor'=>'integer','balance_after_minor'=>'integer','metadata'=>'array']; public function account(){return $this->belongsTo(SavingsAccount::class,'savings_account_id');} }