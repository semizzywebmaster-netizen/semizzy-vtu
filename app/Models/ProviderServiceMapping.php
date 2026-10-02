<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;

class ProviderServiceMapping extends Model
{
    protected $fillable = ['api_provider_id', 'service_id', 'service_key', 'provider_service_id', 'capabilities', 'enabled'];

    protected function casts(): array
    {
        return ['capabilities' => 'array', 'enabled' => 'boolean'];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ApiProvider::class, 'api_provider_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
