<?php
namespace Semizzy\Addons\EventsEntertainment\Models;
use Illuminate\Database\Eloquent\Model;
class EventOrderItem extends Model{protected $table='event_order_items';protected $guarded=[];public function order(){return $this->belongsTo(EventOrder::class,'order_id');}public function ticketType(){return $this->belongsTo(EventTicketType::class,'ticket_type_id');}public function occurrence(){return $this->belongsTo(EventOccurrence::class,'occurrence_id');}public function tickets(){return $this->hasMany(EventTicket::class,'order_item_id');}}
