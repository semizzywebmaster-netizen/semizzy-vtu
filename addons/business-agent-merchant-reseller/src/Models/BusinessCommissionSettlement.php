<?php
namespace Addons\BusinessAgentMerchantReseller\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessCommissionSettlement extends Model
{
 protected $table='business_commission_settlements';
 protected $guarded=[];
 protected $casts=['metadata'=>'array','settled_at'=>'datetime'];
 public function sourcePartner(){return $this->belongsTo(BusinessPartner::class,'business_partner_id');}
 public function beneficiaryPartner(){return $this->belongsTo(BusinessPartner::class,'beneficiary_partner_id');}
}