<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VtuConversionRequest extends Model
{
    protected $fillable = [
        'uuid','reference','user_id','conversion_type','network','status',
        'source_amount_minor','target_amount_minor','fee_minor','currency','rate',
        'source_payload','target_payload','receiving_account','proof_path',
        'operator_id','financial_operation_id','settlement_key','operator_note',
        'rejection_reason','verified_at','approved_at','completed_at','metadata',
    ];

    protected function casts(): array
    {
        return [
            'source_amount_minor'=>'string',
            'target_amount_minor'=>'string',
            'fee_minor'=>'string',
            'rate'=>'decimal:8',
            'source_payload'=>'array',
            'target_payload'=>'array',
            'metadata'=>'array',
            'verified_at'=>'datetime',
            'approved_at'=>'datetime',
            'completed_at'=>'datetime',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function operator(): BelongsTo { return $this->belongsTo(User::class, 'operator_id'); }
    public function financialOperation(): BelongsTo { return $this->belongsTo(FinancialOperation::class); }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['completed','rejected','cancelled'], true);
    }
}
