<?php
namespace Semizzy\Addons\Education\Models;
use Illuminate\Database\Eloquent\Model;
final class EducationLibraryPurchase extends Model {
 protected $table='education_library_purchases';
 protected $guarded=[];
 protected $casts=['amount_minor'=>'integer'];
 public function item(){return $this->belongsTo(EducationLibraryItem::class,'item_id');}
 public function user(){return $this->belongsTo(\App\Models\User::class,'user_id');}
}
