<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialOperation extends Model
{
    protected $fillable=['uuid','reference','user_id','type','status','amount_minor','currency','idempotency_key','provider_reference','metadata'];
    protected function casts(): array { return ['amount_minor'=>'string','metadata'=>'array']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
