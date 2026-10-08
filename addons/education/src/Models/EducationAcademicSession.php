<?php
namespace Semizzy\Addons\Education\Models;
use Illuminate\Database\Eloquent\Model;
final class EducationAcademicSession extends Model { protected $table='education_academic_sessions'; protected $guarded=[]; protected $casts=['is_current'=>'boolean','metadata'=>'array']; public function programmeAdmissions(){return $this->hasMany(EducationProgrammeAdmission::class,'academic_session_id');}}
