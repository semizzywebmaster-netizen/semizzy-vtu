<?php
namespace Semizzy\Addons\TravelTickets\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TravelBookingAttempt extends Model{
 protected $table='travel_booking_attempts';
 protected $guarded=[];
 protected $casts=['response'=>'array'];
 public function booking():BelongsTo{return $this->belongsTo(TravelBooking::class,'travel_booking_id');}
}