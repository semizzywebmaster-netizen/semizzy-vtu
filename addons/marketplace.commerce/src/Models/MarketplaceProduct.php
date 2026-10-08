<?php
namespace Semizzy\Addons\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceProduct extends Model
{
    protected $table = 'marketplace_products';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'price_minor' => 'string',
            'stock_quantity' => 'string',
            'requires_shipping' => 'boolean',
            'download_limit' => 'integer',
            'service_delivery_days' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'seller_id');
    }

    public function isPhysical(): bool { return $this->product_type === 'physical'; }
    public function isDigital(): bool { return $this->product_type === 'digital'; }
    public function isService(): bool { return $this->product_type === 'service'; }
}
