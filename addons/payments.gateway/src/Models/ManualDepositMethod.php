<?php

namespace Semizzy\Addons\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManualDepositMethod extends Model
{
    protected $fillable = [
        'name','bank_name','account_name','account_number','instructions',
        'currency','enabled','priority',
    ];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'priority' => 'integer'];
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(ManualDeposit::class, 'method_id');
    }
}
