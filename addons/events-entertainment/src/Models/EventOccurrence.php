<?php
namespace Semizzy\Addons\EventsEntertainment\Models;
use Illuminate\Database\Eloquent\Model;
class EventOccurrence extends Model {protected $table='event_occurrences';protected $guarded=[];protected $casts=['starts_at'=>'datetime','ends_at'=>'datetime'];public function event(){return $this->belongsTo(Event::class);}public function venue(){return $this->belongsTo(EventVenue::class,'venue_id');}}
