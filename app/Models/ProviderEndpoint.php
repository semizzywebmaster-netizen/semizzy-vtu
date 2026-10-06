<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
class ProviderEndpoint extends Model {
 use SoftDeletes;
 protected $fillable=['api_provider_id','name','operation','method','path','full_url','content_type','auth_mode','headers','query_params','request_mapping','response_mapping','error_mapping','webhook_config','enabled'];
 protected function casts():array{return ['headers'=>'array','query_params'=>'array','request_mapping'=>'array','response_mapping'=>'array','error_mapping'=>'array','webhook_config'=>'array','enabled'=>'boolean'];}
 public function provider():BelongsTo{return $this->belongsTo(ApiProvider::class,'api_provider_id');}
}