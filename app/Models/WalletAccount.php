<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class WalletAccount extends Model
{
    protected $fillable = ['user_id','currency','available_minor','held_minor','status'];

    protected function casts(): array
    {
        return ['available_minor' => 'string', 'held_minor' => 'string'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    protected static function booted(): void
    {
        static::creating(fn (self $wallet) => self::validateWallet($wallet));
        static::updating(fn (self $wallet) => self::validateWallet($wallet));
    }

    private static function validateWallet(self $wallet): void
    {
        $available = ltrim((string) ($wallet->available_minor ?? '0'), '0') ?: '0';
        $held = ltrim((string) ($wallet->held_minor ?? '0'), '0') ?: '0';
        $currency = strtoupper(trim((string) ($wallet->currency ?? '')));
        if (!preg_match('/^\\d+$/', $available) || !preg_match('/^\\d+$/', $held)) {
            throw new InvalidArgumentException('Wallet balances must be non-negative integer minor-unit values.');
        }
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Wallet currency must be a three-letter ISO-style code.');
        }
        if (!in_array($wallet->status, ['active', 'frozen', 'closed'], true)) {
            throw new InvalidArgumentException('Wallet status is invalid.');
        }
        $wallet->available_minor = $available;
        $wallet->held_minor = $held;
        $wallet->currency = $currency;
    }
}
