<?php
namespace Semizzy\Addons\Social\Models;
use Illuminate\Database\Eloquent\Model;
class SocialNumberSms extends Model {
 protected $table='social_number_sms';
 protected $fillable=['order_id','sender','message','provider_message_id','received_at','read_at','metadata'];
 protected $casts=['metadata'=>'array','received_at'=>'datetime','read_at'=>'datetime'];
 public function order(){return $this->belongsTo(SocialServiceOrder::class,'order_id');}
}