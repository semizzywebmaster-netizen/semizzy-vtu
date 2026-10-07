<?php
namespace Semizzy\Addons\GiftCards\Models;
use Illuminate\Database\Eloquent\Model;
class GiftCardOrder extends Model {
 protected $table='gift_card_orders';
 protected $guarded=[];
 protected $casts=['face_value'=>'decimal:2','fee'=>'decimal:2','total'=>'decimal:2','request_data'=>'array','provider_response'=>'array','delivery_data'=>'array','fulfilled_at'=>'datetime','failed_at'=>'datetime'];
}