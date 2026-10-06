<?php

namespace Semizzy\Addons\SimHosting\Models;

use Illuminate\Database\Eloquent\Model;

class SimHostingRental extends Model
{
    protected $table = 'sim_hosting_rentals';
    protected $guarded = [];
    protected $casts = ['amount_minor'=>'integer','metadata'=>'array','starts_at'=>'datetime','expires_at'=>'datetime','renewed_at'=>'datetime'];
    public function product(){ return $this->belongsTo(SimHostingProduct::class, 'sim_hosting_product_id'); }
    public function number(){ return $this->belongsTo(SimHostingNumber::class, 'sim_hosting_number_id'); }
    public function user(){ return $this->belongsTo(\App\Models\User::class); }
}