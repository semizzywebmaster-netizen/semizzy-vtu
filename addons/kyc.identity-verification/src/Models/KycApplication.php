<?php

namespace Semizzy\Addons\Kyc\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KycApplication extends Model
{
    protected $table = 'kyc_applications';

    protected $fillable = [
        'user_id', 'identity_type', 'identity_number', 'status',
        'submitted_at', 'reviewed_at', 'reviewed_by', 'rejection_reason', 'provider_id', 'provider_reference', 'verification_status', 'verification_checked_at',
    ];

    protected $casts = [
        'identity_number' => 'encrypted',
        'verification_checked_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function documents(): HasMany { return $this->hasMany(KycDocument::class); }
}
