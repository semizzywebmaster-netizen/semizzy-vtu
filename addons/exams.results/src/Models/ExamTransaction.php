<?php
namespace Semizzy\Addons\Exams\Models;
use Illuminate\Database\Eloquent\Model;
final class ExamTransaction extends Model {
 protected $table='exam_transactions'; protected $guarded=[];
 protected $casts=['amount_minor'=>'integer','result_payload'=>'array'];
 public function product(){return $this->belongsTo(ExamProduct::class,'product_id');}
 public function user(){return $this->belongsTo(\App\Models\User::class);}
}
