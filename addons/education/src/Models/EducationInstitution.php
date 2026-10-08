<?php
namespace Semizzy\Addons\Education\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
final class EducationInstitution extends Model {
 use SoftDeletes;
 protected $table='education_institutions'; protected $guarded=[];
 protected $casts=['active'=>'boolean','metadata'=>'array','classification'=>'array'];
 public function products(){return $this->hasMany(EducationProduct::class,'institution_id');}
}