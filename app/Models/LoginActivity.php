<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginActivity extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id','identifier','status','ip_address','user_agent','risk_level','reason','created_at'];

    protected function casts(): array { return ['created_at'=>'datetime']; }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}