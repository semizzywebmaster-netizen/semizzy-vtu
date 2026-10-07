<?php
namespace Semizzy\Addons\Social\Models;
use Illuminate\Database\Eloquent\Model;
class SocialAccountInventory extends Model {
 protected $table='social_account_inventory';
 protected $fillable=['platform','title','username','country_code','account_age_days','followers','niche','description','fulfillment_mode','provider_reference','price','currency','status','credentials','metadata'];
 protected $casts=['credentials'=>'encrypted:array','metadata'=>'array','price'=>'decimal:2'];
}