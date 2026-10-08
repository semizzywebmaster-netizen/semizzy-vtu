<?php
namespace Semizzy\Addons\Education\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class EducationExamBody extends Model {
 use SoftDeletes;
 protected $table='education_exam_bodies';
 protected $guarded=[];
 protected $casts=['programmes'=>'array','services'=>'array','metadata'=>'array','active'=>'boolean'];
 public function pastQuestions(){return $this->hasMany(EducationPastQuestion::class,'exam_body_id');}
 public function products(){return $this->hasMany(EducationProduct::class,'exam_body_id');}
}