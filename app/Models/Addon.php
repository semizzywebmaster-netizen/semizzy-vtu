<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Addon extends Model
{
    use SoftDeletes;

    public const LIFECYCLE_STATES = [
        'draft', 'validating', 'installing', 'installed', 'active',
        'inactive', 'failed', 'archived',
    ];

    protected $fillable = [
        'identifier', 'name', 'version', 'status', 'compatibility_constraint',
        'dependencies', 'permissions', 'navigation', 'settings_schema', 'manifest',
        'package_checksum', 'last_error', 'installed_at', 'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'dependencies' => 'array',
            'permissions' => 'array',
            'navigation' => 'array',
            'settings_schema' => 'array',
            'manifest' => 'array',
            'installed_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function lifecycleEvents(): HasMany
    {
        return $this->hasMany(AddonLifecycleEvent::class);
    }

    public function canTransitionTo(string $next): bool
    {
        $transitions = [
            'draft' => ['validating', 'archived'],
            'validating' => ['installing', 'failed', 'inactive'],
            'installing' => ['installed', 'failed', 'inactive'],
            'installed' => ['active', 'inactive', 'archived'],
            'active' => ['inactive', 'failed'],
            'inactive' => ['validating', 'active', 'archived'],
            'failed' => ['validating', 'inactive', 'archived'],
            'archived' => [],
        ];

        return in_array($next, $transitions[$this->status] ?? [], true);
    }
}
