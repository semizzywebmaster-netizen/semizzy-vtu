<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class FxRateProvider extends Model
{
    protected $fillable = [
        'name','code','driver','base_url','credentials','settings','priority','weight',
        'enabled','paused','maintenance','failure_count','cooldown_until',
        'last_success_at','last_failure_at','last_checked_at','last_error',
    ];

    protected $casts = [
        'settings' => 'array',
        'enabled' => 'boolean',
        'paused' => 'boolean',
        'maintenance' => 'boolean',
        'cooldown_until' => 'datetime',
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    public function setCredentialsAttribute($value): void
    {
        $this->attributes['credentials'] = $value === null || $value === '' ? null : Crypt::encryptString(is_string($value) ? $value : json_encode($value));
    }

    public function getCredentialsAttribute($value): array
    {
        if (!$value) return [];
        try {
            $decoded = json_decode(Crypt::decryptString($value), true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function isAvailable(): bool
    {
        return $this->enabled && !$this->paused && !$this->maintenance
            && (!$this->cooldown_until || $this->cooldown_until->isPast());
    }
}