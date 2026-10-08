<?php

namespace Semizzy\Addons\CryptoPayments\Models;

use Illuminate\Database\Eloquent\Model;

class CryptoPaymentWebhookEvent extends Model
{
    protected $table = 'crypto_payment_webhook_events';
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}