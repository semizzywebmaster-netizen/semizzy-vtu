<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;

class UserPasskey extends Model
{
    protected $fillable = [
        'user_id',
        'user_device_id',
        'credential_id',
        'credential_source',
        'name',
        'transports',
        'last_used_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'transports' => 'array',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function device(): BelongsTo { return $this->belongsTo(UserDevice::class, 'user_device_id'); }
}
