<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProviderRoutingRule extends Model {
 protected $fillable=['api_provider_id','scope_type','scope_id','priority','max_attempts','timeout_seconds','enabled','conditions'];
 protected function casts():array{return ['enabled'=>'boolean','conditions'=>'array'];}
 public function provider():BelongsTo{return $this->belongsTo(ApiProvider::class,'api_provider_id');}
}