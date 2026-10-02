<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityEvent extends \Illuminate\Database\Eloquent\Model
{
    protected $fillable = [
        'user_id', 'event', 'severity', 'request_id', 'ip_address', 'user_agent', 'context',
    ];

    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
