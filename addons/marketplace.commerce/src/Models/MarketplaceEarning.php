<?php

namespace Semizzy\Addons\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceEarning extends Model
{
    protected $table = 'marketplace_earnings';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'gross_minor' => 'string',
            'fee_minor' => 'string',
            'net_minor' => 'string',
            'gross_amount_minor' => 'string',
            'category_profit_bps' => 'integer',
            'category_profit_fixed_minor' => 'string',
            'platform_profit_minor' => 'string',
            'seller_net_minor' => 'string',
            'calculation_snapshot' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'order_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'seller_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCategory::class, 'category_id');
    }
}
