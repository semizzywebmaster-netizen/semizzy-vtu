<?php
namespace Semizzy\Addons\Social\Models;
use Illuminate\Database\Eloquent\Model;
class SocialVerificationMethod extends Model { protected $table='social_verification_methods'; protected $fillable=['method_key','name','mode','active','metadata']; protected $casts=['active'=>'boolean','metadata'=>'array'];}
