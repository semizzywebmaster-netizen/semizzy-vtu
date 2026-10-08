<?php
namespace Semizzy\Addons\Education\Models;
use Illuminate\Database\Eloquent\Model;
final class EducationAdmissionOLevelSubject extends Model {
 protected $table='education_admission_olevel_subjects'; protected $guarded=[]; protected $casts=['metadata'=>'array','active'=>'boolean','verified_at'=>'datetime','published_at'=>'datetime','retrieved_at'=>'datetime'];
}