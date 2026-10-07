<?php
namespace Semizzy\Addons\Rewards\Models;
use Illuminate\Database\Eloquent\Model;
class RewardReferral extends Model {
 protected $table='reward_referrals';
 protected $fillable=['referrer_id','referred_id','referral_code_id','status','source','qualified_at','rewarded_at','metadata'];
 protected $casts=['qualified_at'=>'datetime','rewarded_at'=>'datetime','metadata'=>'array'];
 public function code(){return $this->belongsTo(RewardReferralCode::class,'referral_code_id');}
 public function referrer(){return $this->belongsTo(\App\Models\User::class,'referrer_id');}
 public function referred(){return $this->belongsTo(\App\Models\User::class,'referred_id');}
}