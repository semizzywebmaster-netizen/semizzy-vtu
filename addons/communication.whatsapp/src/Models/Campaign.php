<?php
namespace App\Models\Communication;
use Illuminate\Database\Eloquent\Model;
class Campaign extends Model {
 protected $table='communication_campaigns'; protected $guarded=[];
 protected $casts=['audience'=>'array','scheduled_at'=>'datetime','started_at'=>'datetime','completed_at'=>'datetime'];
 public function template(){return $this->belongsTo(Template::class,'template_id');}
 public function creator(){return $this->belongsTo(\App\Models\User::class,'created_by');}
}