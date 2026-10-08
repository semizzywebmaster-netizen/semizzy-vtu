<?php

namespace Semizzy\Addons\CryptoPayments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class CryptoPaymentProvider extends Model
{
    protected $table = 'crypto_payment_providers';

    protected $guarded = [];

    protected $casts = [
        'capabilities' => 'array',
        'supported_assets' => 'array',
        'supported_networks' => 'array',
        'settings' => 'array',
        'enabled' => 'boolean',
        'paused' => 'boolean',
        'maintenance' => 'boolean',
        'cooldown_until' => 'datetime',
        'last_health_check_at' => 'datetime',
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
    ];

    public function setCredentialsAttribute($value): void
    {
        $this->attributes['credentials'] = $value === null ? null : Crypt::encryptString(json_encode($value));
    }

    public function getCredentialsAttribute($value): ?array
    {
        if ($value === null) {
            return null;
        }

        try {
            $decoded = json_decode(Crypt::decryptString($value), true);
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? [], true);
    }

    public function isAvailable(): bool
    {
        return $this->enabled
            && ! $this->paused
            && ! $this->maintenance
            && ($this->cooldown_until === null || $this->cooldown_until->isPast());
    }
}