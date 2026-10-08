<?php
namespace Semizzy\Addons\Education\Models;
use Illuminate\Database\Eloquent\Model;
final class EducationAdmissionDirectEntryQualification extends Model { protected $table='education_admission_direct_entry_qualifications'; protected $guarded=[]; protected $casts=['relevant_field_required'=>'boolean','active'=>'boolean']; public function requirement(){return $this->belongsTo(EducationAdmissionRequirement::class,'admission_requirement_id');}}
