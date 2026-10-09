<?php

namespace Semizzy\Addons\SchoolAdmission\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SchoolAdmissionInstitution extends Model
{
    use SoftDeletes;

    protected $table = 'school_admission_institutions';

    protected $fillable = ['slug','name','institution_type','country_code','state','official_website','admissions_url','logo_path','active','metadata'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'metadata' => 'array'];
    }

    public function programmes(): HasMany
    {
        return $this->hasMany(SchoolAdmissionProgramme::class, 'institution_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(SchoolAdmissionProduct::class, 'institution_id');
    }
}