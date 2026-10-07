<?php
namespace Semizzy\Addons\GiftCards\Models;
use Illuminate\Database\Eloquent\Model;
class GiftCardDelivery extends Model {
 protected $table='gift_card_deliveries'; protected $guarded=[];
 protected $casts=['code_encrypted'=>'encrypted','pin_encrypted'=>'encrypted','metadata'=>'array','first_revealed_at'=>'datetime','last_revealed_at'=>'datetime','expires_at'=>'datetime'];
}