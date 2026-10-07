<?php
namespace Semizzy\Addons\Rewards\Models;
use Illuminate\Database\Eloquent\Model;
class RewardEvent extends Model {
 protected $table='reward_events';
 protected $fillable=['user_id','rule_id','event_key','operation_key','status','amount','currency','wallet_reference','metadata'];
 protected $casts=['metadata'=>'array','amount'=>'decimal:2'];
}