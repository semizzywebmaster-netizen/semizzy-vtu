<?php
namespace Semizzy\Addons\Education\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
final class EducationProduct extends Model {
 use SoftDeletes;
 protected $table='education_products'; protected $guarded=[];
 protected $casts=['provider_amount_minor'=>'integer','selling_amount_minor'=>'integer','requires_institution'=>'boolean','requires_student_reference'=>'boolean','requires_session'=>'boolean','active'=>'boolean','fields'=>'array','metadata'=>'array'];
 public function institution(){return $this->belongsTo(EducationInstitution::class,'institution_id');}
 public function transactions(){return $this->hasMany(EducationTransaction::class,'education_product_id');}
 public function provider(){return $this->belongsTo(\App\Models\ApiProvider::class,'provider_id');}
}