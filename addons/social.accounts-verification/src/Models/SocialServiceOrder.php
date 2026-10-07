<?php
namespace Semizzy\Addons\Social\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
class SocialServiceOrder extends Model {
 protected $table='social_service_orders';
 protected $fillable=['reference','user_id','order_type','inventory_id','status','amount','currency','provider_reference','provider_id','delivered_at','expires_at','metadata'];
 protected $casts=['metadata'=>'array','delivered_at'=>'datetime','expires_at'=>'datetime','amount'=>'decimal:2'];
 public function user(){return $this->belongsTo(User::class);}
 public function sms(){return $this->hasMany(SocialNumberSms::class,'order_id');}
}