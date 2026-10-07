<?php
namespace Semizzy\Addons\TravelTickets\Models;
use Illuminate\Database\Eloquent\Model;
class TravelService extends Model {protected $table='travel_services';protected $guarded=[];protected $casts=['enabled'=>'boolean','requirements'=>'array'];}