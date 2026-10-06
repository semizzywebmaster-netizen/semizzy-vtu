<?php

namespace Semizzy\Addons\SimHosting\Models;

use App\Models\ApiProvider;
use Illuminate\Database\Eloquent\Model;

class SimHostingProduct extends Model
{
    protected $table = 'sim_hosting_products';
    protected $guarded = [];
    protected $casts = ['rental_price_minor'=>'integer','renewal_price_minor'=>'integer','active'=>'boolean','metadata'=>'array'];
    public function numbers(){ return $this->hasMany(SimHostingNumber::class, 'sim_hosting_product_id'); }
    public function provider(){ return $this->belongsTo(ApiProvider::class, 'provider_id'); }
}
