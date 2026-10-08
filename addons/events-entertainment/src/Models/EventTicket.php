<?php
namespace Semizzy\Addons\EventsEntertainment\Models;
use Illuminate\Database\Eloquent\Model;
class EventTicket extends Model{protected $table='event_tickets';protected $guarded=[];protected $casts=['checked_in_at'=>'datetime'];public function orderItem(){return $this->belongsTo(EventOrderItem::class,'order_item_id');}public function event(){return $this->belongsTo(Event::class);}public function ticketType(){return $this->belongsTo(EventTicketType::class,'ticket_type_id');}public function occurrence(){return $this->belongsTo(EventOccurrence::class,'occurrence_id');}public function user(){return $this->belongsTo(\App\Models\User::class);}public function checkedInBy(){return $this->belongsTo(\App\Models\User::class,'checked_in_by');}}
