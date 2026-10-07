<?php
namespace Semizzy\Addons\Social\Models;
use Illuminate\Database\Eloquent\Model;
class SocialPlatform extends Model { protected $table='social_platforms'; protected $fillable=['platform_key','name','verification_mode','active','requirements','metadata']; protected $casts=['active'=>'boolean','requirements'=>'array','metadata'=>'array']; public function mappings(){return $this->hasMany(SocialProviderMapping::class);}}
