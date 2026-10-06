<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderProductMappingV2 extends Model
{
    protected $table = 'provider_product_mappings_v2';

    protected $fillable = [
        'api_provider_id','provider_service_id','catalogue_product_id',
        'catalogue_product_type','priority','enabled','mapping_status','metadata',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'enabled' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ApiProvider::class, 'api_provider_id');
    }

    public function providerService(): BelongsTo
    {
        return $this->belongsTo(ProviderService::class, 'provider_service_id');
    }

    public function catalogueProduct(): BelongsTo
    {
        return $this->belongsTo(ServiceProduct::class, 'catalogue_product_id');
    }
}
