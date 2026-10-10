<?php
namespace Addons\CommunicationWhatsapp\Models;
use Illuminate\Database\Eloquent\Model;
class Consent extends Model {
 protected $table='communication_consents'; protected $guarded=[];
 protected $casts=['opted_in'=>'boolean','consented_at'=>'datetime','revoked_at'=>'datetime'];
 public function user(){return $this->belongsTo(\App\Models\User::class);}
}