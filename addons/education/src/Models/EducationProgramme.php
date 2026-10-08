<?php
namespace Semizzy\Addons\Education\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes;
final class EducationProgramme extends Model { use SoftDeletes; protected $table='education_programmes'; protected $guarded=[]; protected $casts=['active'=>'boolean','metadata'=>'array','verified_at'=>'datetime']; public function institution(){return $this->belongsTo(EducationInstitution::class);} public function academicUnit(){return $this->belongsTo(EducationAcademicUnit::class,'academic_unit_id');} public function department(){return $this->belongsTo(EducationDepartment::class);} public function admissionSessions(){return $this->hasMany(EducationProgrammeAdmission::class,'programme_id');}}
