<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderIdempotencyRecord extends Model
{
    protected $fillable = [
        'api_provider_id','idempotency_key','request_hash','state','internal_reference',
        'provider_reference','transaction_status','safe_response','safe_error',
        'locked_until','completed_at',
    ];

    protected $hidden = ['safe_response','safe_error'];

    protected function casts(): array
    {
        return [
            'safe_response'=>'array',
            'locked_until'=>'datetime',
            'completed_at'=>'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ApiProvider::class,'api_provider_id');
    }
}