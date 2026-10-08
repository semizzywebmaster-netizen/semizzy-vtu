<?php
namespace Semizzy\Addons\Education\Models;
use Illuminate\Database\Eloquent\Model;
final class EducationAdmissionSource extends Model { protected $table='education_admission_sources'; protected $guarded=[]; protected $casts=['retrieved_at'=>'datetime']; public function programmeAdmission(){return $this->belongsTo(EducationProgrammeAdmission::class,'programme_admission_id');} public function requirement(){return $this->belongsTo(EducationAdmissionRequirement::class,'admission_requirement_id');}}
