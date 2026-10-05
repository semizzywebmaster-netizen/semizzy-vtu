<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPermissionOverride extends Model
{
    protected $fillable = ['user_id', 'permission', 'allowed'];

    protected function casts(): array
    {
        return ['allowed' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
