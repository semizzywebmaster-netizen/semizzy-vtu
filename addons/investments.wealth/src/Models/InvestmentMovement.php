<?php
namespace Semizzy\Addons\Investments\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class InvestmentMovement extends Model { protected $table='investment_movements'; protected $guarded=[]; protected function casts(): array{return ['amount_minor'=>'integer','balance_after_minor'=>'integer','metadata'=>'array'];} public function account(): BelongsTo{return $this->belongsTo(InvestmentAccount::class,'investment_account_id');} }