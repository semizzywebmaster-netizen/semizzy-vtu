<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CacOrderDocument extends Model
{
    protected $fillable = [
        'cac_order_id', 'document_type', 'storage_disk', 'storage_path', 'original_name',
        'mime_type', 'size_bytes', 'status', 'checksum', 'review_note', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(CacOrder::class, 'cac_order_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
