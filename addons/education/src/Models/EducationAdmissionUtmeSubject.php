<?php
namespace Semizzy\Addons\Education\Models;
use Illuminate\Database\Eloquent\Model;
final class EducationAdmissionUtmeSubject extends Model { protected $table='education_admission_utme_subjects'; protected $guarded=[]; protected $casts=['compulsory'=>'boolean']; public function combination(){return $this->belongsTo(EducationAdmissionUtmeCombination::class,'combination_id');}}
