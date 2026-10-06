<?php
namespace Semizzy\Addons\Savings\Models;
use Illuminate\Database\Eloquent\Model;
class SavingsPlan extends Model { protected $table='savings_plans'; protected $guarded=[]; protected $casts=['active'=>'boolean','allow_early_withdrawal'=>'boolean','metadata'=>'array','interest_rate'=>'decimal:4','early_withdrawal_penalty'=>'decimal:4']; }