<?php

namespace Semizzy\Addons\Payments\Models;

use Illuminate\Database\Eloquent\Model;

final class PaymentWebhookEvent extends Model
{
    protected $table = 'payment_webhook_events';
    protected $fillable = ['provider_key','event_id','event_type','signature_hash','processing_status','payment_reference','payload','processing_error','processed_at'];
    protected function casts(): array { return ['payload'=>'array','processed_at'=>'datetime']; }
}