<?php
namespace Semizzy\Addons\Exams\Models;
use Illuminate\Database\Eloquent\Model;
final class ExamProduct extends Model {
 protected $table='exam_products'; protected $guarded=[];
 protected $casts=['price_minor'=>'integer','max_attempts'=>'integer','active'=>'boolean'];
 public function provider(){return $this->belongsTo(\App\Models\ApiProvider::class,'provider_id');}
 public function transactions(){return $this->hasMany(ExamTransaction::class,'product_id');}
}
