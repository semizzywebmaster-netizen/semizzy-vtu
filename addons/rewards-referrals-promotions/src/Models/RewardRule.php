<?php
namespace Semizzy\Addons\Rewards\Models;
use Illuminate\Database\Eloquent\Model;
class RewardRule extends Model {
 protected $table='reward_rules';
 protected $fillable=['name','event_key','reward_type','amount','currency','active','max_global_redemptions','max_user_redemptions','conditions','metadata'];
 protected $casts=['active'=>'boolean','conditions'=>'array','metadata'=>'array','amount'=>'decimal:2'];
}