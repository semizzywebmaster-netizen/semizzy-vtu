<?php

namespace Semizzy\Addons\ForexDigitalAssets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForexDigitalAssetProvider extends Model
{
    protected $table = 'forex_digital_asset_providers';
    protected $guarded = [];

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
