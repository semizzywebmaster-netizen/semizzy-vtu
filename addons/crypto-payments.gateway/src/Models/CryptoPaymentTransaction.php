<?php

namespace Semizzy\Addons\CryptoPayments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CryptoPaymentTransaction extends Model
{
    protected $table = 'crypto_payment_transactions';
    protected $guarded = [];

    protected $casts = [
        'fiat_amount' => 'decimal:8',
        'crypto_amount' => 'decimal:18',
        'crypto_received' => 'decimal:18',
        'exchange_rate' => 'decimal:18',
        'provider_fee' => 'decimal:18',
        'provider_payload' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->uuid ??= (string) Str::uuid();
        });
    }
}