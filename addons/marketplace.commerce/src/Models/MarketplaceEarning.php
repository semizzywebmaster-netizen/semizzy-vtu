<?php
namespace Semizzy\Addons\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceEarning extends Model
{
    protected $table='marketplace_earnings';
    protected $guarded=[];
    protected function casts(): array { return ['gross_minor'=>'string','fee_minor'=>'string','net_minor'=>'string']; }
    public function order(): BelongsTo { return $this->belongsTo(MarketplaceOrder::class,'order_id'); }
    public function seller(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'seller_id'); }
}
