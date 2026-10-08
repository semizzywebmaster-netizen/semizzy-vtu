<?php
namespace Semizzy\Addons\Investments\Models;

use Illuminate\Database\Eloquent\Model;

class InvestmentCorporateAction extends Model
{
    protected $table = 'investment_corporate_actions';
    protected $guarded = [];
    protected $casts = [
        'record_date' => 'date',
        'ex_date' => 'date',
        'payment_date' => 'date',
        'value' => 'decimal:8',
        'metadata' => 'array',
    ];

    public function security(){return $this->belongsTo(InvestmentSecurity::class,'security_id');}
    public function provider(){return $this->belongsTo(InvestmentProvider::class,'provider_id');}
}