<?php

namespace Semizzy\Addons\Savings\Models;

use Illuminate\Database\Eloquent\Model;

class SavingsAccount extends Model
{
    protected $table = 'savings_accounts';
    protected $guarded = [];

    protected $casts = [
        'target_amount_minor' => 'string',
        'balance_minor' => 'string',
        'earned_minor' => 'string',
        'matures_at' => 'datetime',
        'last_contribution_at' => 'datetime',
        'next_contribution_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function plan()
    {
        return $this->belongsTo(SavingsPlan::class, 'savings_plan_id');
    }
}