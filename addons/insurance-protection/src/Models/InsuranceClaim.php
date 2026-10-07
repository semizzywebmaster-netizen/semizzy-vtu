<?php
namespace Addons\InsuranceProtection\Models;
use Illuminate\Database\Eloquent\Model;
class InsuranceClaim extends Model { protected $table='insurance_claims'; protected $guarded=[]; protected $casts=['documents'=>'array','provider_snapshot'=>'array','submitted_at'=>'datetime','resolved_at'=>'datetime']; public function policy(){return $this->belongsTo(InsurancePolicy::class,'insurance_policy_id');} }