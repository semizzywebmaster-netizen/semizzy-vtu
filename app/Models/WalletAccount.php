<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletAccount extends Model
{
    protected $fillable = ['user_id','currency','available_minor','held_minor','status'];

    protected function casts(): array
    {
        return ['available_minor' => 'integer', 'held_minor' => 'integer'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
