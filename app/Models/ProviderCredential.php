<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProviderCredential extends Model {
 protected $fillable=['provider_connection_id','field_key','label','field_type','required','secret','placement','header_name','query_name','body_path','prefix','value'];
 protected $hidden=['value'];
 protected function casts():array{return ['required'=>'boolean','secret'=>'boolean','value'=>'encrypted'];}
 public function connection():BelongsTo{return $this->belongsTo(ProviderConnection::class,'provider_connection_id');}
}