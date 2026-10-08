<?php
namespace Semizzy\Addons\Education\Models;
use Illuminate\Database\Eloquent\Model;
final class EducationAdmissionOLevelRequirement extends Model { protected $table='education_admission_olevel_requirements'; protected $guarded=[]; protected $casts=['awaiting_result_allowed'=>'boolean','required_english'=>'boolean','required_mathematics'=>'boolean']; public function requirement(){return $this->belongsTo(EducationAdmissionRequirement::class,'admission_requirement_id');} public function subjects(){return $this->hasMany(EducationAdmissionOLevelSubject::class,'olevel_requirement_id');}}
