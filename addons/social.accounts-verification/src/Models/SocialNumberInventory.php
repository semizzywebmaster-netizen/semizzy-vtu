<?php
namespace Semizzy\Addons\Social\Models;
use Illuminate\Database\Eloquent\Model;
class SocialNumberInventory extends Model {
 protected $table='social_number_inventory';
 protected $fillable=['country_code','country_name','service_key','phone_number','phone_hash','fulfillment_mode','provider_reference','price','currency','status','expires_at','metadata'];
 protected $casts=['phone_number'=>'encrypted','metadata'=>'array','expires_at'=>'datetime','price'=>'decimal:2'];
}