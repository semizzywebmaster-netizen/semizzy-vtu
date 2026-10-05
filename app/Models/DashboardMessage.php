<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DashboardMessage extends Model
{
    protected $fillable = [
        'type','title','message','tiers','audiences','time_period','season_key','priority','active','starts_at','ends_at',
    ];

    protected function casts(): array
    {
        return [
            'tiers' => 'array',
            'audiences' => 'array',
            'active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        $now = now();
        return $query->where('active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }
}