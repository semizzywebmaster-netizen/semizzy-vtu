<?php
namespace Semizzy\Addons\Rewards\Models;
use Illuminate\Database\Eloquent\Model;
class RewardCouponRedemption extends Model {
 protected $table='reward_coupon_redemptions';
 protected $fillable=['coupon_id','user_id','operation_key','discount_value','currency','metadata'];
 protected $casts=['metadata'=>'array','discount_value'=>'decimal:2'];
}