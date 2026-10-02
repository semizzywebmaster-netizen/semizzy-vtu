<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookReceipt extends \Illuminate\Database\Eloquent\Model
{
    protected $fillable = [
        'api_provider_id',
        'event_id',
        'payload_hash',
        'signature_hash',
        'status',
        'received_at',
        'processed_at',
        'processing_error',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ApiProvider::class, 'api_provider_id');
    }
}
