<?php
namespace Semizzy\Addons\Education\Models;
use Illuminate\Database\Eloquent\Model;
final class EducationAdmissionRoute extends Model { protected $table='education_admission_routes'; protected $guarded=[]; protected $casts=['active'=>'boolean']; public function programmeAdmissions(){return $this->belongsToMany(EducationProgrammeAdmission::class,'education_programme_admission_routes','admission_route_id','programme_admission_id');} public function requirements(){return $this->hasMany(EducationAdmissionRequirement::class,'admission_route_id');}}
