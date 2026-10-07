<?php
namespace App\Models\Communication;
use Illuminate\Database\Eloquent\Model;
class Provider extends Model {
 protected $table='communication_providers'; protected $guarded=[];
 protected $casts=['credentials'=>'encrypted:array','capabilities'=>'array','enabled'=>'boolean','paused'=>'boolean','cooldown_until'=>'datetime','last_success_at'=>'datetime','last_failure_at'=>'datetime'];
}