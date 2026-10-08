<?php
namespace Semizzy\Addons\EventsEntertainment\Models;
use Illuminate\Database\Eloquent\Model;
class EventVenue extends Model {protected $table='event_venues';protected $guarded=[];public function events(){return $this->hasMany(Event::class,'venue_id');}}
