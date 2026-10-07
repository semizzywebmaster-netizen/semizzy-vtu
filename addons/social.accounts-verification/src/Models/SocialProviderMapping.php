<?php
namespace Semizzy\Addons\Social\Models;
use Illuminate\Database\Eloquent\Model;
class SocialProviderMapping extends Model { protected $table='social_provider_mappings'; protected $fillable=['social_platform_id','api_provider_id','capability','provider_service_id','enabled','metadata']; protected $casts=['enabled'=>'boolean','metadata'=>'array']; public function platform(){return $this->belongsTo(SocialPlatform::class,'social_platform_id');} public function provider(){return $this->belongsTo(\App\Models\ApiProvider::class,'api_provider_id');}}
