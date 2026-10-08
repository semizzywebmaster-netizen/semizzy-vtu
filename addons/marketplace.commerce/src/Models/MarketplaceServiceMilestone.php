<?php

namespace Semizzy\Addons\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceServiceMilestone extends Model
{
    protected $table = 'marketplace_service_milestones';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_at'=>'datetime','submitted_at'=>'datetime','accepted_at'=>'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'order_id');
    }
}
