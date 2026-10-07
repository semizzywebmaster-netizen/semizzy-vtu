<?php
namespace Semizzy\Addons\Rewards\Models;
use Illuminate\Database\Eloquent\Model;
class RewardCampaign extends Model {
 protected $table='reward_campaigns';
 protected $fillable=['name','code','type','status','starts_at','ends_at','rules','metadata'];
 protected $casts=['starts_at'=>'datetime','ends_at'=>'datetime','rules'=>'array','metadata'=>'array'];
}