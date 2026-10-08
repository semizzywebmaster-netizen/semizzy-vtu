<?php
namespace Semizzy\Addons\Education\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes;
final class EducationDepartment extends Model { use SoftDeletes; protected $table='education_departments'; protected $guarded=[]; protected $casts=['active'=>'boolean','metadata'=>'array','verified_at'=>'datetime']; public function institution(){return $this->belongsTo(EducationInstitution::class);} public function academicUnit(){return $this->belongsTo(EducationAcademicUnit::class,'academic_unit_id');} public function programmes(){return $this->hasMany(EducationProgramme::class,'department_id');}}
