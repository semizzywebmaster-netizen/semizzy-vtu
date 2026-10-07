<?php
namespace Semizzy\Addons\Government\Models;
use Illuminate\Database\Eloquent\Model;
class GovernmentCertificate extends Model {
 protected $table='government_certificates';
 protected $fillable=['application_id','certificate_type','certificate_number','disk','path','issued_at','expires_at','status','metadata'];
 protected $casts=['metadata'=>'array','issued_at'=>'datetime','expires_at'=>'datetime'];
 public function application(){return $this->belongsTo(GovernmentApplication::class,'application_id');}
}