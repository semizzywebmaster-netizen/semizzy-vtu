<?php
namespace Semizzy\Addons\Marketplace\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class MarketplaceOrder extends Model {
 protected $table='marketplace_orders'; protected $guarded=[];
 protected function casts(): array { return ['metadata'=>'array','quantity'=>'string','unit_price_minor'=>'string','total_minor'=>'string']; }
 public function buyer(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'buyer_id'); }
 public function seller(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'seller_id'); }
 public function product(): BelongsTo { return $this->belongsTo(MarketplaceProduct::class,'product_id'); }
}