<?php
namespace Addons\BusinessAgentMerchantReseller\Models;
use Illuminate\Database\Eloquent\Model;
class BusinessPartner extends Model{protected $table='business_partners';protected $guarded=[];protected $casts=['metadata'=>'array'];public function user(){return $this->belongsTo(\App\Models\User::class);}public function business(){return $this->belongsTo(BusinessProfile::class,'business_profile_id');}public function events(){return $this->hasMany(BusinessPartnerEvent::class);}}
