<?php

namespace Semizzy\Addons\SimHosting\Models;

use Illuminate\Database\Eloquent\Model;

class SimHostingMovement extends Model
{
    protected $table = 'sim_hosting_movements';
    protected $guarded = [];
    protected $casts = ['amount_minor'=>'integer','metadata'=>'array'];
    public function rental(){ return $this->belongsTo(SimHostingRental::class, 'sim_hosting_rental_id'); }
}