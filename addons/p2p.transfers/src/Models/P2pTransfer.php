<?php
namespace Semizzy\Addons\P2p\Models;
use Illuminate\Database\Eloquent\Model;
class P2pTransfer extends Model {
 protected $table='p2p_transfers'; protected $guarded=[];
 protected $casts=['amount_minor'=>'string','fee_minor'=>'string','metadata'=>'array'];
 public function sender(){return $this->belongsTo(\App\Models\User::class,'sender_id');}
 public function recipient(){return $this->belongsTo(\App\Models\User::class,'recipient_id');}
}