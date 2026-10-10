<?php

namespace Semizzy\Addons\Education\Models;

use Illuminate\Database\Eloquent\Model;

final class EducationInstitutionSyncRun extends Model
{
    protected $table = 'education_institution_sync_runs';

    protected $fillable = [
        'source', 'status', 'pages_processed', 'records_seen', 'records_created',
        'records_updated', 'records_skipped', 'records_rejected',
        'minimum_expected_records', 'expected_total', 'next_cursor',
        'error_summary', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'pages_processed' => 'integer',
            'records_seen' => 'integer',
            'records_created' => 'integer',
            'records_updated' => 'integer',
            'records_skipped' => 'integer',
            'records_rejected' => 'integer',
            'minimum_expected_records' => 'integer',
            'expected_total' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
