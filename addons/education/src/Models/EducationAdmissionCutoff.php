<?php
namespace Semizzy\Addons\Education\Models;
use Illuminate\Database\Eloquent\Model;
final class EducationAdmissionCutoff extends Model { protected $table='education_admission_cutoffs'; protected $guarded=[]; protected $casts=['verified_at'=>'datetime']; public function programmeAdmission(){return $this->belongsTo(EducationProgrammeAdmission::class,'programme_admission_id');}}
