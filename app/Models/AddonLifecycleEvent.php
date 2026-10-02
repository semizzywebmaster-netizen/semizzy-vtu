<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AddonLifecycleEvent extends Model
{
    protected $fillable = [
        'addon_id', 'addon_identifier', 'event', 'from_status', 'to_status',
        'message', 'context', 'actor_id',
    ];

    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
