<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ProviderCategory extends Model {
 protected $fillable=['api_provider_id','external_id','external_name','normalized_key','metadata','status','last_synced_at'];
 protected function casts():array{return ['metadata'=>'array','last_synced_at'=>'datetime'];}
 public function provider():BelongsTo{return $this->belongsTo(ApiProvider::class,'api_provider_id');}
 public function subcategories():HasMany{return $this->hasMany(ProviderSubcategory::class);}
 public function services():HasMany{return $this->hasMany(ProviderService::class);}
}