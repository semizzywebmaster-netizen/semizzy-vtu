<?php

namespace Semizzy\Addons\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class PaymentGatewayProvider extends Model
{
    protected $table = 'payment_gateway_providers';
    protected $guarded = [];

    protected $casts = [
        'capabilities' => 'array',
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
        $this->attributes['credentials'] = $value === null
            ? null
            : Crypt::encryptString(is_string($value) ? $value : json_encode($value));
    }

    public function getCredentialsAttribute($value): array
    {
        if (!$value) {
            return [];
        }

        try {
            $decoded = Crypt::decryptString($value);
            return json_decode($decoded, true) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? [], true);
    }

    public function isAvailable(): bool
    {
        return $this->enabled
            && !$this->paused
            && !$this->maintenance
            && (!$this->cooldown_until || $this->cooldown_until->isPast());
    }
}