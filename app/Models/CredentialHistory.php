<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredentialHistory extends Model
{
    protected $fillable = ['user_id', 'credential_type', 'credential_hash'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}