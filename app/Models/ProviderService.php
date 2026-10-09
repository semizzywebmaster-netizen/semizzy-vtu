<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
class ProviderService extends Model {
 use SoftDeletes;
 protected $hidden=['raw_provider_data'];
 protected $fillable=['api_provider_id','provider_category_id','provider_subcategory_id','external_service_id','external_service_code','name','description','service_type','network','provider_price','currency','status','metadata','raw_provider_data','last_synced_at'];
 protected function casts():array{return ['provider_price'=>'decimal:4','metadata'=>'array','raw_provider_data'=>'array','last_synced_at'=>'datetime'];}
 public function provider():BelongsTo{return $this->belongsTo(ApiProvider::class,'api_provider_id');}
 public function category():BelongsTo{return $this->belongsTo(ProviderCategory::class,'provider_category_id');}
 public function subcategory():BelongsTo{return $this->belongsTo(ProviderSubcategory::class,'provider_subcategory_id');}
 public function imports():HasMany{return $this->hasMany(ProviderServiceImport::class);}
 public function platformMapping():HasOne{return $this->hasOne(ProviderServiceMapping::class,'provider_service_id','id');}
}