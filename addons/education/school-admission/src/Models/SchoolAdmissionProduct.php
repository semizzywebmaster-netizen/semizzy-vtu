<?php

namespace Semizzy\Addons\SchoolAdmission\Models;

use App\Models\ApiProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SchoolAdmissionProduct extends Model
{
    use SoftDeletes;

    protected $table = 'school_admission_products';

    protected $fillable = ['key','institution_id','programme_id','service_type','name','description','currency','price_minor','provider_id','provider_product_code','active','display_order','requirements','metadata'];

    protected function casts(): array
    {
        return ['price_minor' => 'integer','provider_id' => 'integer','active' => 'boolean','display_order' => 'integer','requirements' => 'array','metadata' => 'array'];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(SchoolAdmissionInstitution::class, 'institution_id');
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(SchoolAdmissionProgramme::class, 'programme_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ApiProvider::class, 'provider_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SchoolAdmissionTransaction::class, 'product_id');
    }
}