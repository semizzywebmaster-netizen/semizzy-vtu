<?php
namespace Semizzy\Addons\EventsEntertainment\Models;
use Illuminate\Database\Eloquent\Model;
class EventOrder extends Model{protected $table='event_orders';protected $guarded=[];protected $casts=['paid_at'=>'datetime','cancelled_at'=>'datetime','refunded_at'=>'datetime'];public function user(){return $this->belongsTo(\App\Models\User::class);}public function event(){return $this->belongsTo(Event::class);}public function items(){return $this->hasMany(EventOrderItem::class,'order_id');}}
