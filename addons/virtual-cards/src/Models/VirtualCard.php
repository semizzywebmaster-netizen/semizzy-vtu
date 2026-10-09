<?php
namespace Addons\VirtualCards\Models;
use Illuminate\Database\Eloquent\Model;
class VirtualCard extends Model {
 protected $table='virtual_cards'; protected $guarded=[];
 protected $casts=['metadata'=>'array','spending_limit_minor'=>'integer','spent_minor'=>'integer'];
 public function user(){return $this->belongsTo(\App\Models\User::class);}
 public function transactions(){return $this->hasMany(VirtualCardTransaction::class,'virtual_card_id');}
}