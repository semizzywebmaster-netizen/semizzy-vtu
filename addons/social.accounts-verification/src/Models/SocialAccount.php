<?php
namespace Semizzy\Addons\Social\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SocialAccount extends Model{
 protected $table='social_accounts'; protected $fillable=['user_id','platform','username','account_reference','status','verification_status','verification_method','provider_reference','metadata'];
 protected $casts=['metadata'=>'array'];
 public function user():BelongsTo{return $this->belongsTo(\App\Models\User::class);}
 public function verificationRequests(){return $this->hasMany(SocialVerificationRequest::class);}
}