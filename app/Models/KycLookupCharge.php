<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycLookupCharge extends Model
{
    protected $fillable = [
        'user_id','identity_type','identity_hash','operation_key','charge_minor','currency',
        'status','provider_id','provider_reference','provider_status','wallet_reference',
        'charged_at','refunded_at','metadata',
    ];

    protected function casts(): array
    {
        return [
            'charge_minor' => 'integer',
            'charged_at' => 'datetime',
            'refunded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}