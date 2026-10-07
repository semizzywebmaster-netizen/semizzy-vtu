<?php
namespace Semizzy\Addons\Smm\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class SmmService extends Model {
 protected $table='smm_services'; protected $guarded=[];
 protected function casts():array{return ['min_quantity'=>'integer','max_quantity'=>'integer','unit_price_minor'=>'integer','active'=>'boolean','requirements'=>'array','metadata'=>'array'];}
 public function category():BelongsTo{return $this->belongsTo(SmmServiceCategory::class,'category_id');}
}