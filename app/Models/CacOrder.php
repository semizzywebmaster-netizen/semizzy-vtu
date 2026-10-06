<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CacOrder extends Model
{
    protected $fillable = [
        'uuid','reference','user_id','service_type','status','idempotency_key',
        'customer_name','business_name','company_type','provider_reference',
        'api_provider_id','amount_minor','fee_minor','total_minor','currency',
        'request_payload','response_payload','metadata','failure_message',
        'submitted_at','completed_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'metadata' => 'array',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'amount_minor' => 'integer',
            'fee_minor' => 'integer',
            'total_minor' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(CacServiceProduct::class, 'cac_service_product_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(CacOrderAttempt::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CacOrderDocument::class);
    }
}
