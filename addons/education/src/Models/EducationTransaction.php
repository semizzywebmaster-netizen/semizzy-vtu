<?php
namespace Semizzy\Addons\Education\Models;
use Illuminate\Database\Eloquent\Model;
final class EducationTransaction extends Model {
 protected $table='education_transactions'; protected $guarded=[];
 protected $casts=['amount_minor'=>'integer','provider_amount_minor'=>'integer','customer_data'=>'array','provider_data'=>'array','processed_at'=>'datetime','next_requery_at'=>'datetime','requery_required'=>'boolean'];
 public function product(){return $this->belongsTo(EducationProduct::class,'education_product_id');}
 public function institution(){return $this->belongsTo(EducationInstitution::class,'institution_id');}
 public function user(){return $this->belongsTo(\App\Models\User::class);}
}