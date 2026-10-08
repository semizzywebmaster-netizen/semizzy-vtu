<?php
namespace Semizzy\Addons\Education\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class EducationPastQuestion extends Model {
 use SoftDeletes;
 protected $table='education_past_questions';
 protected $guarded=[];
 protected $casts=['price_minor'=>'integer','metadata'=>'array','active'=>'boolean'];
 public function examBody(){return $this->belongsTo(EducationExamBody::class,'exam_body_id');}
 public function institution(){return $this->belongsTo(EducationInstitution::class,'institution_id');}
}