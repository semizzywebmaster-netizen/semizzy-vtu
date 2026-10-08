<?php
namespace Semizzy\Addons\Education\Models;
use Illuminate\Database\Eloquent\Model;
final class EducationAdmissionScreeningRule extends Model { protected $table='education_admission_screening_rules'; protected $guarded=[]; protected $casts=['required'=>'boolean','registration_required'=>'boolean','first_choice_required'=>'boolean','result_upload_required'=>'boolean','verified_at'=>'datetime']; public function programmeAdmission(){return $this->belongsTo(EducationProgrammeAdmission::class,'programme_admission_id');}}
