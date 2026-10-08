<?php

namespace Semizzy\Addons\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceCategory extends Model
{
    protected $table = 'marketplace_categories';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attribute_schema' => 'array',
            'active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function products(): HasMany
    {
        return $this->hasMany(MarketplaceProduct::class, 'category_id');
    }
}
