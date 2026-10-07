<?php
namespace Semizzy\Addons\Government\Models;
use Illuminate\Database\Eloquent\Model;
class GovernmentDocument extends Model {
 protected $table='government_documents';
 protected $fillable=['application_id','document_type','disk','path','original_name','mime_type','size_bytes','status','reviewed_by','reviewed_at','review_note','metadata'];
 protected $casts=['metadata'=>'array','reviewed_at'=>'datetime'];
 public function application(){return $this->belongsTo(GovernmentApplication::class,'application_id');}
}