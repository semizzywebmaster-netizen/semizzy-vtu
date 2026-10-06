<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CacServiceProduct extends Model
{
    protected $fillable = [
        'identifier','name','service_type','description','currency',
        'provider_price_minor','selling_price_minor','enabled','requirements','metadata',
    ];

    protected function casts(): array
    {
        return [
            'provider_price_minor'=>'integer','selling_price_minor'=>'integer',
            'enabled'=>'boolean','requirements'=>'array','metadata'=>'array',
        ];
    }

    public function providerRoutes(): HasMany
    {
        return $this->hasMany(CacProviderRoute::class);
    }
}
