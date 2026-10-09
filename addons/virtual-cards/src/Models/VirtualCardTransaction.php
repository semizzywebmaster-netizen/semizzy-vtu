<?php
namespace Addons\VirtualCards\Models;
use Illuminate\Database\Eloquent\Model;
class VirtualCardTransaction extends Model {
 protected $table='virtual_card_transactions'; protected $guarded=[];
 protected $casts=['metadata'=>'array','amount_minor'=>'integer','occurred_at'=>'datetime'];
 public function card(){return $this->belongsTo(VirtualCard::class,'virtual_card_id');}
}