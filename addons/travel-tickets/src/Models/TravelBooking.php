<?php
namespace Semizzy\Addons\TravelTickets\Models;
use Illuminate\Database\Eloquent\Model;
class TravelBooking extends Model {protected $table='travel_bookings';protected $guarded=[];protected $casts=['amount'=>'decimal:2','fee'=>'decimal:2','total'=>'decimal:2','search_data'=>'array','passengers'=>'array','booking_data'=>'array','confirmed_at'=>'datetime','cancelled_at'=>'datetime'];}