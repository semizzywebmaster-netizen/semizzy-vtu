<?php
namespace Semizzy\Addons\Investments\Models;
use Illuminate\Database\Eloquent\Model;
class InvestmentProduct extends Model { protected $table='investment_products'; protected $guarded=[]; protected function casts(): array{return ['minimum_amount_minor'=>'integer','maximum_amount_minor'=>'integer','profit_rate'=>'decimal:4','term_days'=>'integer','allow_early_redemption'=>'boolean','early_redemption_penalty'=>'decimal:4','active'=>'boolean','metadata'=>'array'];} }