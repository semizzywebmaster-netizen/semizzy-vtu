<?php
namespace Semizzy\Addons\GiftCards\Models;
use Illuminate\Database\Eloquent\Model;
class GiftCardDenomination extends Model {
 protected $table='gift_card_denominations'; protected $guarded=[];
 protected $casts=['face_value'=>'decimal:2','provider_price'=>'decimal:2','sale_price'=>'decimal:2','enabled'=>'boolean','metadata'=>'array'];
 public function product(){return $this->belongsTo(GiftCardProduct::class,'gift_card_product_id');}
}