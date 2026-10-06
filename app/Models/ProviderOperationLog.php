<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProviderOperationLog extends Model {
 protected $fillable=['api_provider_id','provider_connection_id','operation','method','endpoint','internal_reference','http_status','duration_ms','result','error_code','safe_message','safe_metadata'];
 protected function casts():array{return ['safe_metadata'=>'array'];}
 public function provider():BelongsTo{return $this->belongsTo(ApiProvider::class,'api_provider_id');}
}