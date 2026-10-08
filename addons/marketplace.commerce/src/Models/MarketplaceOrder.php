<?php
namespace Semizzy\Addons\Marketplace\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class MarketplaceOrder extends Model {
 protected $table='marketplace_orders'; protected $guarded=[];
 protected function casts(): array { return ['metadata'=>'array','shipping_address'=>'array','delivery_data'=>'array','quantity'=>'string','unit_price_minor'=>'string','total_minor'=>'string','revision_count'=>'integer','paid_at'=>'datetime','fulfilled_at'=>'datetime','accepted_at'=>'datetime','completed_at'=>'datetime','cancelled_at'=>'datetime','refunded_at'=>'datetime']; }
 public function buyer(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'buyer_id'); }
 public function seller(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'seller_id'); }
 public function product(): BelongsTo { return $this->belongsTo(MarketplaceProduct::class,'product_id'); }
}