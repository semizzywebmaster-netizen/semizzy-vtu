<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ProviderSubcategory extends Model {
 protected $fillable=['provider_category_id','external_id','external_name','normalized_key','metadata','status','last_synced_at'];
 protected function casts():array{return ['metadata'=>'array','last_synced_at'=>'datetime'];}
 public function category():BelongsTo{return $this->belongsTo(ProviderCategory::class,'provider_category_id');}
 public function services():HasMany{return $this->hasMany(ProviderService::class);}
}