<?php

namespace Semizzy\Addons\ForexDigitalAssets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForexDigitalAssetInstrument extends Model
{
    protected $table = 'forex_digital_asset_instruments';
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'enabled' => 'boolean',
        'verified' => 'boolean',
        'source_checked_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function quotes(): HasMany
    {
        return $this->hasMany(ForexDigitalAssetQuote::class, 'instrument_id');
    }

    public function hasRequiredProvenance(): bool
    {
        return filled($this->source_name)
            && filled($this->source_reference)
            && $this->source_checked_at !== null;
    }
}
