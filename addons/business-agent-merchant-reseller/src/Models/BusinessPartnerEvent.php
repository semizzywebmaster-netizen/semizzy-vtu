<?php
namespace Addons\BusinessAgentMerchantReseller\Models;
use Illuminate\Database\Eloquent\Model;
class BusinessPartnerEvent extends Model{protected $table='business_partner_events';protected $guarded=[];protected $casts=['metadata'=>'array'];public function partner(){return $this->belongsTo(BusinessPartner::class,'business_partner_id');}}
