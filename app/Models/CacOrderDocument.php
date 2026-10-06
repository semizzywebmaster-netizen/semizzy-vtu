<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CacOrderDocument extends Model
{
    protected $fillable = [
        'cac_order_id','document_type','storage_disk','storage_path','original_name',
        'mime_type','size_bytes','status','checksum',
    ];

    public function order(): BelongsTo { return $this->belongsTo(CacOrder::class, 'cac_order_id'); }
}
