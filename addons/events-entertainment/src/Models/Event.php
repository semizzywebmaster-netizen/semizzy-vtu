<?php
namespace Semizzy\Addons\EventsEntertainment\Models;
use Illuminate\Database\Eloquent\Model;
class Event extends Model {
 protected $table='events'; protected $guarded=[];
 protected $casts=['starts_at'=>'datetime','ends_at'=>'datetime','published_at'=>'datetime','cancelled_at'=>'datetime','completed_at'=>'datetime','gallery'=>'array','age_policy'=>'array','policies'=>'array'];
 public function organizer(){return $this->belongsTo(EventOrganizer::class,'organizer_id');}
 public function venue(){return $this->belongsTo(EventVenue::class,'venue_id');}
 public function occurrences(){return $this->hasMany(EventOccurrence::class);}
 public function ticketTypes(){return $this->hasMany(EventTicketType::class);}
}
