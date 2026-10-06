<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CacProviderRoute extends Model
{
    protected $fillable = [
        'cac_service_product_id','api_provider_id','priority','enabled','endpoint_map','capabilities',
    ];

    protected function casts(): array
    {
        return ['priority'=>'integer','enabled'=>'boolean','endpoint_map'=>'array','capabilities'=>'array'];
    }

    public function product(): BelongsTo { return $this->belongsTo(CacServiceProduct::class, 'cac_service_product_id'); }
    public function provider(): BelongsTo { return $this->belongsTo(ApiProvider::class, 'api_provider_id'); }
}
