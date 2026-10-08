<?php
namespace Semizzy\Addons\Education\Models;
use Illuminate\Database\Eloquent\Model;
final class EducationAdmissionResultBody extends Model { protected $table='education_admission_result_bodies'; protected $guarded=[]; protected $casts=['accepted'=>'boolean']; public function requirement(){return $this->belongsTo(EducationAdmissionRequirement::class,'admission_requirement_id');}}
