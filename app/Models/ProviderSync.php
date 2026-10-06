<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderSync extends Model
{
    protected $fillable = [
        'api_provider_id','trigger','status','discovered_count','new_count','updated_count',
        'removed_count','price_changed_count','failed_count','summary','error_message',
        'started_at','finished_at',
    ];

    protected $hidden = ['error_message'];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ApiProvider::class, 'api_provider_id');
    }
}
