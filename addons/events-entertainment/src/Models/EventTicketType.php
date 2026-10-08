<?php
namespace Semizzy\Addons\EventsEntertainment\Models;
use Illuminate\Database\Eloquent\Model;
class EventTicketType extends Model {protected $table='event_ticket_types';protected $guarded=[];protected $casts=['sales_start_at'=>'datetime','sales_end_at'=>'datetime','perks'=>'array','is_active'=>'boolean'];public function event(){return $this->belongsTo(Event::class);}public function occurrence(){return $this->belongsTo(EventOccurrence::class,'occurrence_id');}}
