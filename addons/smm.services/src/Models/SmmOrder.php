<?php
namespace Semizzy\Addons\Smm\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class SmmOrder extends Model {
 protected $table='smm_orders'; protected $guarded=[];
 protected function casts():array{return ['quantity'=>'integer','amount_minor'=>'integer','metadata'=>'array','completed_at'=>'datetime'];}
 public function user():BelongsTo{return $this->belongsTo(User::class);}
 public function service():BelongsTo{return $this->belongsTo(SmmService::class,'service_id');}
}