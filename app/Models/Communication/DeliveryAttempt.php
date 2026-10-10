<?php

namespace App\Models\Communication;

use Illuminate\Database\Eloquent\Model;

class DeliveryAttempt extends Model
{
    protected $table = 'communication_delivery_attempts';
    protected $guarded = [];
    protected $casts = ['started_at' => 'datetime', 'completed_at' => 'datetime'];

    public function message() { return $this->belongsTo(Message::class, 'message_id'); }
    public function provider() { return $this->belongsTo(Provider::class, 'provider_id'); }
}
