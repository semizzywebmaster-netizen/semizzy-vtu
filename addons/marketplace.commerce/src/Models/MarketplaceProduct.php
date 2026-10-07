<?php
namespace Semizzy\Addons\Marketplace\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class MarketplaceProduct extends Model {
 protected $table='marketplace_products'; protected $guarded=[];
 protected function casts(): array { return ['metadata'=>'array','price_minor'=>'string','stock_quantity'=>'string']; }
 public function seller(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'seller_id'); }
}