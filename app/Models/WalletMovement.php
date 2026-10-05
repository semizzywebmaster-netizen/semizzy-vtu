<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use RuntimeException;

class WalletMovement extends Model
{
    protected $fillable = [
        'wallet_account_id','operation_key','reference','type','amount_minor','currency',
        'available_before_minor','available_after_minor','held_before_minor','held_after_minor','metadata',
    ];

    protected function casts(): array { return ['metadata' => 'array']; }

    public function wallet(): BelongsTo { return $this->belongsTo(WalletAccount::class, 'wallet_account_id'); }

    protected static function booted(): void
    {
        static::creating(function (self $movement): void {
            foreach (['operation_key','reference','type','currency'] as $field) {
                if (trim((string) $movement->{$field}) === '') throw new RuntimeException("Wallet movement {$field} is required.");
            }
            if (!preg_match('/^[A-Z]{3}$/', strtoupper((string) $movement->currency))) throw new RuntimeException('Wallet movement currency is invalid.');
            foreach (['amount_minor','available_before_minor','available_after_minor','held_before_minor','held_after_minor'] as $field) {
                if (!preg_match('/^\d+$/', (string) $movement->{$field})) throw new RuntimeException('Wallet movement amounts must be non-negative integer minor units.');
                $movement->{$field} = ltrim((string) $movement->{$field}, '0') ?: '0';
            }
            $movement->currency = strtoupper((string) $movement->currency);
        });
        static::updating(fn (): never => throw new LogicException('Wallet movements are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Wallet movements cannot be deleted.'));
    }
}
