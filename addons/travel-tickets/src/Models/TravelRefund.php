<?php

namespace Semizzy\Addons\TravelTickets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Semizzy\Addons\TravelTickets\Models\TravelBooking;

class TravelRefund extends Model
{
    protected $table = 'travel_refunds';
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(TravelBooking::class, 'travel_booking_id');
    }
}