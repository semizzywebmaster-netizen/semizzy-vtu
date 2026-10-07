<?php
namespace Semizzy\Addons\SpinToWin\Models;
use Illuminate\Database\Eloquent\Model;
class SpinPlay extends Model {
 public function prize(){return $this->belongsTo(SpinPrize::class,'prize_id');}
 protected $table='spin_plays'; protected $guarded=[];
 protected function casts():array{return ['played_at'=>'datetime','rewarded_at'=>'datetime','metadata'=>'array'];}
}