<?php
namespace Addons\BusinessAgentMerchantReseller\Models;
use Illuminate\Database\Eloquent\Model;
class BusinessProfile extends Model{protected $table='business_profiles';protected $guarded=[];protected $casts=['metadata'=>'array'];public function user(){return $this->belongsTo(\App\Models\User::class);}public function partners(){return $this->hasMany(BusinessPartner::class);}}
