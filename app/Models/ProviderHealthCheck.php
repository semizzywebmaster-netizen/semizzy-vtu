<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProviderHealthCheck extends Model {
 protected $fillable=['api_provider_id','provider_connection_id','status','http_status','response_time_ms','message','checked_at'];
 protected function casts():array{return ['checked_at'=>'datetime'];}
 public function provider():BelongsTo{return $this->belongsTo(ApiProvider::class,'api_provider_id');}
}