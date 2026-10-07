<?php
namespace Semizzy\Addons\GiftCards\Models;
use Illuminate\Database\Eloquent\Model;
class GiftCardOrder extends Model {
 protected $table='gift_card_orders'; protected $guarded=[];
 protected $casts=['face_value'=>'decimal:2','fee'=>'decimal:2','total'=>'decimal:2','request_data'=>'array','provider_response'=>'array','delivery_data'=>'array','product_snapshot'=>'array','provider_snapshot'=>'array','fulfilled_at'=>'datetime','failed_at'=>'datetime','provider_pending_at'=>'datetime'];
 public function product(){return $this->belongsTo(GiftCardProduct::class,'gift_card_product_id');}
 public function delivery(){return $this->hasOne(GiftCardDelivery::class,'gift_card_order_id');}
}