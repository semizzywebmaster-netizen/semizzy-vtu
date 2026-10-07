<?php
namespace App\Models\Communication;
use Illuminate\Database\Eloquent\Model;
class Conversation extends Model {
 protected $table='communication_conversations'; protected $guarded=[];
 protected $casts=['last_message_at'=>'datetime'];
 public function user(){return $this->belongsTo(\App\Models\User::class);}
 public function messages(){return $this->hasMany(Message::class,'conversation_id');}
}