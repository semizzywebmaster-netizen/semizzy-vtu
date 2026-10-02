<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderRequestLog extends Model
{
    public $timestamps = false;
    protected $fillable=['api_provider_id','operation','service_key','status','provider_reference','idempotency_key','duration_ms','request_summary','response_summary'];
    public function provider(): BelongsTo { return $this->belongsTo(ApiProvider::class,'api_provider_id'); }
}
