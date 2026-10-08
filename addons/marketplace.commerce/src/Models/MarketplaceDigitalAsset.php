<?php

namespace Semizzy\Addons\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceDigitalAsset extends Model
{
    protected $table = 'marketplace_digital_assets';
    protected $guarded = [];

    public function product(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProduct::class, 'product_id');
    }
}
