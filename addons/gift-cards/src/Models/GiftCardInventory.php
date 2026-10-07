<?php
namespace Semizzy\Addons\GiftCards\Models;
use Illuminate\Database\Eloquent\Model;
class GiftCardInventory extends Model {
 protected $table='gift_card_inventory';
 protected $guarded=[];
 protected $casts=['code_encrypted'=>'encrypted','pin_encrypted'=>'encrypted','metadata'=>'array','sold_at'=>'datetime'];
}