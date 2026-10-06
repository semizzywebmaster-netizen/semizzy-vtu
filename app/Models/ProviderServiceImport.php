<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProviderServiceImport extends Model {
 protected $fillable=['api_provider_id','provider_service_id','selection_scope','imported','approved','auto_sync_allowed','state','last_imported_at'];
 protected function casts():array{return ['imported'=>'boolean','approved'=>'boolean','auto_sync_allowed'=>'boolean','last_imported_at'=>'datetime'];}
 public function provider():BelongsTo{return $this->belongsTo(ApiProvider::class,'api_provider_id');}
 public function service():BelongsTo{return $this->belongsTo(ProviderService::class,'provider_service_id');}
}