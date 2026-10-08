<?php

namespace Semizzy\Addons\ForexDigitalAssets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class ForexDigitalAssetProvider extends Model
{
    protected $table = 'forex_digital_asset_providers';
    protected $guarded = [];

    public function setCredentialsAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['credentials'] = null;
            return;
        }

        $payload = is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR);
        $this->attributes['credentials'] = Crypt::encryptString($payload);
    }

    public function getCredentialsAttribute($value): array
    {
        if (! $value) {
            return [];
        }

        try {
            $payload = Crypt::decryptString($value);
            $decoded = json_decode($payload, true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    protected $casts = [
        'capabilities' => 'array',
        'settings' => 'array',
        'enabled' => 'boolean',
        'verified' => 'boolean',
        'paused' => 'boolean',
        'maintenance' => 'boolean',
        'is_market_data_provider' => 'boolean',
        'is_execution_provider' => 'boolean',
        'last_health_check_at' => 'datetime',
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
    ];

    public function quotes(): HasMany
    {
        return $this->hasMany(ForexDigitalAssetQuote::class, 'provider_id');
    }

    public function canProvideMarketData(): bool
    {
        return $this->enabled && $this->verified && $this->is_market_data_provider
            && ! $this->paused && ! $this->maintenance;
    }

    public function canExecute(): bool
    {
        return $this->enabled && $this->verified && $this->is_execution_provider
            && ! $this->paused && ! $this->maintenance;
    }
}
