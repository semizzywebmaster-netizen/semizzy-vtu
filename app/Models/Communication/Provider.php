<?php

namespace App\Models\Communication;

use Illuminate\Database\Eloquent\Model;

/** Provider credentials and routing settings for communication channels. */
class Provider extends Model
{
    protected $table = 'communication_providers';

    protected $fillable = [
        'channel', 'name', 'driver', 'credentials', 'capabilities', 'priority', 'weight',
        'enabled', 'paused', 'failure_count', 'cooldown_until', 'last_success_at', 'last_failure_at',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'capabilities' => 'array',
            'enabled' => 'boolean',
            'paused' => 'boolean',
            'priority' => 'integer',
            'weight' => 'integer',
            'failure_count' => 'integer',
            'cooldown_until' => 'datetime',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
        ];
    }
}
