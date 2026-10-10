<?php

namespace Semizzy\\Addons\\Education\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\SoftDeletes;

final class EducationInstitution extends Model
{
    use SoftDeletes;

    protected $table = 'education_institutions';

    protected $fillable = [
        'code', 'source_key', 'name', 'type', 'category', 'ownership',
        'accrediting_body', 'established_year', 'city', 'state', 'lga',
        'country', 'website', 'external_id', 'source_url', 'import_source',
        'review_status', 'reviewed_by', 'reviewed_at', 'review_notes',
        'active', 'last_synced_at', 'source_hash', 'classification', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'established_year' => 'integer',
            'reviewed_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'classification' => 'array',
            'metadata' => 'array',
        ];
    }

    public function scopeApproved($query)
    {
        return $query->where('review_status', 'approved')->where('active', true);
    }
}
