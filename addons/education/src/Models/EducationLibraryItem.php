<?php
namespace Semizzy\Addons\Education\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
final class EducationLibraryItem extends Model {
 use SoftDeletes;
 protected $table='education_library_items';
 protected $guarded=[];
 protected $casts=['is_free'=>'boolean','price_minor'=>'integer','file_size'=>'integer','exam_year'=>'integer','downloads_count'=>'integer','published_at'=>'datetime'];
 public function purchases(){return $this->hasMany(EducationLibraryPurchase::class,'item_id');}
 public function uploader(){return $this->belongsTo(\App\Models\User::class,'uploaded_by');}
 public function scopePublished($query){return $query->where('status','published')->whereNotNull('published_at');}
 public function scopeCategory($query,string $category){return $query->where('category',$category);}
}
