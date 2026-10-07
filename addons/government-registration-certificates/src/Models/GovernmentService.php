<?php
namespace Semizzy\Addons\Government\Models;
use Illuminate\Database\Eloquent\Model;
class GovernmentService extends Model {
 protected $table='government_services';
 protected $fillable=['service_key','name','agency','description','fulfillment_mode','provider_reference','price','currency','status','requirements','metadata'];
 protected $casts=['requirements'=>'array','metadata'=>'array','price'=>'decimal:2'];
}