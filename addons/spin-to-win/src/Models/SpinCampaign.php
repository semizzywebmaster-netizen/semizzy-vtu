<?php
namespace Semizzy\Addons\SpinToWin\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class SpinCampaign extends Model {
 protected $table='spin_campaigns';
 protected $guarded=[];
 protected function casts():array{return ['starts_at'=>'datetime','ends_at'=>'datetime','eligibility'=>'array','tier_restrictions'=>'array','metadata'=>'array'];}
 public function prizes():HasMany{return $this->hasMany(SpinPrize::class,'campaign_id');}
 public function plays():HasMany{return $this->hasMany(SpinPlay::class,'campaign_id');}
}