<?php

namespace Semizzy\\Addons\\SchoolAdmission\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;
use Illuminate\\Database\\Eloquent\\Relations\\HasMany;

final class SchoolAdmissionProgramme extends Model
{
    protected $table = 'school_admission_programmes';

    protected $fillable = ['institution_id','code','name','level','study_mode','active','metadata'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'metadata' => 'array'];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(SchoolAdmissionInstitution::class, 'institution_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(SchoolAdmissionProduct::class, 'programme_id');
    }
}