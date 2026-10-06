<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileChangeRequest extends \Illuminate\Database\Eloquent\Model
{
    protected $fillable = [
        'user_id','request_type','status','requested_changes','reason',
        'business_entity_type','business_registration_number','business_tax_id',
        'business_registered_name','business_registered_address','business_state',
        'business_country','business_documents','admin_note','reviewed_by','reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_changes' => 'array',
            'business_documents' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
