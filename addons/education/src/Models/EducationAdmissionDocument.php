<?php
namespace Semizzy\Addons\Education\Models;
use Illuminate\Database\Eloquent\Model;
final class EducationAdmissionDocument extends Model { protected $table='education_admission_documents'; protected $guarded=[]; protected $casts=['required'=>'boolean']; public function programmeAdmission(){return $this->belongsTo(EducationProgrammeAdmission::class,'programme_admission_id');}}
