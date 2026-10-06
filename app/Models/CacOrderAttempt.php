<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CacOrderAttempt extends Model
{
    protected $fillable = [
        'cac_order_id','attempt_number','api_provider_id','operation','status',
        'provider_reference','request_payload','response_payload','error_code','error_message',
    ];

    protected function casts(): array
    {
        return ['request_payload'=>'array','response_payload'=>'array'];
    }

    public function order(): BelongsTo { return $this->belongsTo(CacOrder::class, 'cac_order_id'); }
}
