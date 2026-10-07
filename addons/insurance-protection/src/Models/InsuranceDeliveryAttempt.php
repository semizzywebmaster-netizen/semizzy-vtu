<?php
namespace Addons\InsuranceProtection\Models;
use Illuminate\Database\Eloquent\Model;
class InsuranceDeliveryAttempt extends Model { protected $table='insurance_delivery_attempts'; protected $guarded=[]; protected $casts=['started_at'=>'datetime','completed_at'=>'datetime']; public function provider(){return $this->belongsTo(InsuranceProvider::class,'insurance_provider_id');} }