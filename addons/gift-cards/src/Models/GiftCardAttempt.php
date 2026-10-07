<?php
namespace Semizzy\Addons\GiftCards\Models;
use Illuminate\Database\Eloquent\Model;
class GiftCardAttempt extends Model {
 protected $table='gift_card_attempts';
 protected $guarded=[];
 protected $casts=['response'=>'array'];
}