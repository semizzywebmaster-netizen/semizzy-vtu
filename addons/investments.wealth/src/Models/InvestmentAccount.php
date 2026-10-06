<?php
namespace Semizzy\Addons\Investments\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class InvestmentAccount extends Model { protected $table='investment_accounts'; protected $guarded=[]; protected function casts(): array{return ['principal_minor'=>'integer','profit_minor'=>'integer','redeemed_minor'=>'integer','funded_at'=>'datetime','matures_at'=>'datetime','redeemed_at'=>'datetime','metadata'=>'array'];} public function product(): BelongsTo{return $this->belongsTo(InvestmentProduct::class,'investment_product_id');} }