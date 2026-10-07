<?php
namespace Addons\InsuranceProtection\Models;
use Illuminate\Database\Eloquent\Model;
class InsuranceProduct extends Model { protected $table='insurance_products'; protected $guarded=[]; protected $casts=['coverage'=>'array','eligibility'=>'array','metadata'=>'array','active'=>'boolean']; public function provider(){return $this->belongsTo(InsuranceProvider::class,'insurance_provider_id');} public function policies(){return $this->hasMany(InsurancePolicy::class);} }