<?php

namespace Semizzy\Addons\P2p\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class P2pTradeListing extends Model
{
    protected $table = 'p2p_trade_listings';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount_minor' => 'string', 'price_minor' => 'string', 'metadata' => 'array'];
    }

    public function seller(): BelongsTo { return $this->belongsTo(User::class, 'seller_id'); }
    public function offers(): HasMany { return $this->hasMany(P2pTradeOffer::class, 'listing_id'); }
}