<?php

namespace Semizzy\Addons\Payments\Models;

use App\Models\User;
use App\Models\WalletAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentIntent extends Model
{
    protected $fillable = ['user_id','wallet_account_id','reference','provider_reference','provider_id','purpose','currency','amount_minor','status','channel','checkout_url','expires_at','paid_at','refunded_at','metadata'];

    protected function casts(): array
    {
        return ['amount_minor' => 'string','metadata' => 'array','expires_at' => 'datetime','paid_at' => 'datetime','refunded_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function walletAccount(): BelongsTo { return $this->belongsTo(WalletAccount::class); }
}
