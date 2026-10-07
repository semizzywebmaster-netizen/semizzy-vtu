<?php
namespace Semizzy\Addons\Social\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SocialVerificationRequest extends Model{
 protected $table='social_verification_requests'; protected $fillable=['social_account_id','user_id','reference','method','status','provider_reference','evidence','metadata','expires_at','verified_at'];
 protected $casts=['metadata'=>'array','expires_at'=>'datetime','verified_at'=>'datetime'];
 public function socialAccount():BelongsTo{return $this->belongsTo(SocialAccount::class);}
 public function user():BelongsTo{return $this->belongsTo(\App\Models\User::class);}
}