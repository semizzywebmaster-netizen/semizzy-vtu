<?php
namespace Semizzy\Addons\SpinToWin\Models;
use Illuminate\Database\Eloquent\Model;
class SpinPrize extends Model {
 protected $table='spin_prizes'; protected $guarded=[];
 protected function casts():array{return ['weight'=>'decimal:6','active'=>'boolean','eligibility'=>'array','metadata'=>'array'];}
}