<?php
namespace Semizzy\Addons\GiftCards\Models;
use Illuminate\Database\Eloquent\Model;
class GiftCardProduct extends Model {
 protected $table='gift_card_products'; protected $guarded=[];
 protected $casts=['denominations'=>'array','metadata'=>'array','enabled'=>'boolean','provider_price'=>'decimal:2','sale_price'=>'decimal:2','min_amount'=>'decimal:2','max_amount'=>'decimal:2'];
 public function provider(){return $this->belongsTo(GiftCardProvider::class,'gift_card_provider_id');}
 public function denominationOptions(){return $this->hasMany(GiftCardDenomination::class,'gift_card_product_id');}
}