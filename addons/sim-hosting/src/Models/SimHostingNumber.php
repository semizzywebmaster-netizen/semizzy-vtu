<?php

namespace Semizzy\Addons\SimHosting\Models;

use Illuminate\Database\Eloquent\Model;

class SimHostingNumber extends Model
{
    protected $table = 'sim_hosting_numbers';
    protected $guarded = [];
    protected $casts = ['metadata'=>'array','last_checked_at'=>'datetime'];
    public function product(){ return $this->belongsTo(SimHostingProduct::class, 'sim_hosting_product_id'); }
    public function rentals(){ return $this->hasMany(SimHostingRental::class, 'sim_hosting_number_id'); }
}