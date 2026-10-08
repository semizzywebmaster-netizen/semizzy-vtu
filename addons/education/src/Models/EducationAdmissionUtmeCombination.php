<?php
namespace Semizzy\Addons\Education\Models;
use Illuminate\Database\Eloquent\Model;
final class EducationAdmissionUtmeCombination extends Model { protected $table='education_admission_utme_combinations'; protected $guarded=[]; protected $casts=['active'=>'boolean']; public function requirement(){return $this->belongsTo(EducationAdmissionRequirement::class,'admission_requirement_id');} public function subjects(){return $this->hasMany(EducationAdmissionUtmeSubject::class,'combination_id');}}
