<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsernameHistory extends Model
{
    protected $fillable = ['user_id','username','reason','changed_by'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class,'changed_by'); }
}