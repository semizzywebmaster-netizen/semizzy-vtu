<?php
namespace Semizzy\Addons\EventsEntertainment\Models;
use Illuminate\Database\Eloquent\Model;
class EventOrganizer extends Model {protected $table='event_organizers';protected $guarded=[];protected $casts=['verified_at'=>'datetime','submitted_at'=>'datetime'];public function user(){return $this->belongsTo(\App\Models\User::class);}public function events(){return $this->hasMany(Event::class,'organizer_id');}}
