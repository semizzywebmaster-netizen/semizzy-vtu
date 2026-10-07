<?php
namespace Semizzy\Addons\Rewards\Models;
use Illuminate\Database\Eloquent\Model;
class RewardCoupon extends Model {
 protected $table='reward_coupons';
 protected $fillable=['campaign_id','code','discount_type','discount_value','currency','max_redemptions','per_user_limit','redemptions_count','active','starts_at','ends_at','conditions'];
 protected $casts=['active'=>'boolean','starts_at'=>'datetime','ends_at'=>'datetime','conditions'=>'array','discount_value'=>'decimal:2'];
}