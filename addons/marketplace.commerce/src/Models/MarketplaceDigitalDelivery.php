<?php

namespace Semizzy\Addons\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceDigitalDelivery extends Model
{
    protected $table = 'marketplace_digital_deliveries';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_downloaded_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'order_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MarketplaceDigitalAsset::class, 'asset_id');
    }
}
