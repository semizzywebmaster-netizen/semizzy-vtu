<?php

namespace Semizzy\Addons\Kyc\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycDocument extends Model
{
    protected $table = 'kyc_documents';

    protected $fillable = [
        'kyc_application_id', 'document_type', 'disk', 'path',
        'original_name', 'mime_type', 'size', 'sha256', 'status',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(KycApplication::class, 'kyc_application_id');
    }
}
