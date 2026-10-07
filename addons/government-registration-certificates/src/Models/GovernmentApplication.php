<?php
namespace Semizzy\Addons\Government\Models;
use Illuminate\Database\Eloquent\Model;
class GovernmentApplication extends Model {
 protected $table='government_applications';
 protected $fillable=['reference','user_id','service_id','status','amount','currency','provider_reference','provider_id','payment_reference','payment_status','paid_at','submitted_at','completed_at','expires_at','application_data','metadata'];
 protected $casts=['application_data'=>'array','metadata'=>'array','amount'=>'decimal:2','paid_at'=>'datetime','submitted_at'=>'datetime','completed_at'=>'datetime','expires_at'=>'datetime'];
 public function service(){return $this->belongsTo(GovernmentService::class,'service_id');}
 public function documents(){return $this->hasMany(GovernmentDocument::class,'application_id');}
 public function certificates(){return $this->hasMany(GovernmentCertificate::class,'application_id');}
 public function user(){return $this->belongsTo(\App\Models\User::class,'user_id');}
}