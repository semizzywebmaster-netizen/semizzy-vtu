<?php
namespace Addons\CommunicationWhatsapp\Models;
use Illuminate\Database\Eloquent\Model;
class Template extends Model {
 protected $table='communication_templates'; protected $guarded=[];
 protected $casts=['variables'=>'array','enabled'=>'boolean'];
}