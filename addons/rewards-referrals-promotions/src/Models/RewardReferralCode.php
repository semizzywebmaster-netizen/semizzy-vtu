<?php
namespace Semizzy\Addons\Rewards\Models;
use Illuminate\Database\Eloquent\Model;
class RewardReferralCode extends Model {
 protected $table='reward_referral_codes';
 protected $fillable=['user_id','code','active','uses_count','max_uses'];
 protected $casts=['active'=>'boolean'];
 public function user(){return $this->belongsTo(\App\Models\User::class);}
}