<?php

namespace Semizzy\Addons\Payments\Models;

use App\Models\User;
use App\Models\WalletAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualDeposit extends Model
{
    protected $fillable = [
        'user_id','wallet_account_id','method_id','reference','amount_minor','currency',
        'status','proof_path','proof_original_name','user_note','admin_note','reviewed_by',
        'submitted_at','reviewed_at','credited_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'string',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'credited_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function walletAccount(): BelongsTo { return $this->belongsTo(WalletAccount::class); }
    public function method(): BelongsTo { return $this->belongsTo(ManualDepositMethod::class, 'method_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
