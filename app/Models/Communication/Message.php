<?php

namespace App\Models\Communication;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $table = 'communication_messages';
    protected $guarded = [];

    protected $casts = ['metadata' => 'array', 'sent_at' => 'datetime', 'delivered_at' => 'datetime', 'failed_at' => 'datetime'];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function attempts()
    {
        return $this->hasMany(DeliveryAttempt::class, 'message_id');
    }
}
