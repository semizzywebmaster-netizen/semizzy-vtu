<?php
namespace Semizzy\Addons\GiftCards\Models;
use Illuminate\Database\Eloquent\Model;
class GiftCardRefund extends Model {
 protected $table='gift_card_refunds';
 protected $guarded=[];
 protected $casts=['amount'=>'decimal:2'];
}