<?php
namespace Semizzy\Addons\Investments\Models;

use Illuminate\Database\Eloquent\Model;

class InvestmentMarketQuote extends Model
{
    protected $table = 'investment_market_quotes';
    protected $guarded = [];
    protected $casts = [
        'bid' => 'decimal:8',
        'ask' => 'decimal:8',
        'last_price' => 'decimal:8',
        'open_price' => 'decimal:8',
        'high_price' => 'decimal:8',
        'low_price' => 'decimal:8',
        'volume' => 'integer',
        'observed_at' => 'datetime',
        'expires_at' => 'datetime',
        'raw_metadata' => 'array',
    ];

    public function security(){return $this->belongsTo(InvestmentSecurity::class,'security_id');}
    public function provider(){return $this->belongsTo(InvestmentProvider::class,'provider_id');}

    public function isFresh(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}