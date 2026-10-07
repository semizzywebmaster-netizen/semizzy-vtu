<?php

namespace Semizzy\Addons\P2p\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class P2pTradeOffer extends Model
{
    protected $table = 'p2p_trade_offers';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'string',
            'price_minor' => 'string',
            'metadata' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo { return $this->belongsTo(P2pTradeListing::class, 'listing_id'); }
    public function buyer(): BelongsTo { return $this->belongsTo(User::class, 'buyer_id'); }
    public function seller(): BelongsTo { return $this->belongsTo(User::class, 'seller_id'); }
}