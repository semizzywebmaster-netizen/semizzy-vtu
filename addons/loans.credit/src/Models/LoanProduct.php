<?php
namespace Semizzy\Addons\Loans\Models;
use IlluminateDatabaseEloquentModel;
class LoanProduct extends Model { protected $table='loan_products'; protected $guarded=[]; protected $casts=['minimum_amount_minor'=>'integer','maximum_amount_minor'=>'integer','interest_rate'=>'decimal:4','late_penalty_rate'=>'decimal:4','tenure_days'=>'integer','active'=>'boolean','metadata'=>'array']; }