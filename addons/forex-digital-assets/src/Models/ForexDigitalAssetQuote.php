<?php

namespace Semizzy\Addons\ForexDigitalAssets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForexDigitalAssetQuote extends Model
{
    protected $table = 'forex_digital_asset_quotes';
    protected $guarded = [];

    protected $casts = [
        'bid' => 'decimal:12',
        'ask' => 'decimal:12',
        'mid' => 'decimal:12',
        'open' => 'decimal:12',
        'high' => 'decimal:12',
        'low' => 'decimal:12',
        'close' => 'decimal:12',
        'volume' => 'decimal:12',
        'observed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(ForexDigitalAssetInstrument::class, 'instrument_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ForexDigitalAssetProvider::class, 'provider_id');
    }

    public function isFresh(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
