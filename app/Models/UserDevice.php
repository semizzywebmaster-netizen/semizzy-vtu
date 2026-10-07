<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDevice extends Model
{
    protected $fillable = [
        'user_id',
        'device_key',
        'name',
        'ip_address',
        'user_agent',
        'last_seen_at',
        'revoked_at',
        'authenticated_at',
        'auth_method',
        'biometric_enabled',
        'session_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
            'authenticated_at' => 'datetime',
            'biometric_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
