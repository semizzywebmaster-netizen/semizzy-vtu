<?php

namespace Semizzy\Addons\SchoolAdmission\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SchoolAdmissionTransaction extends Model
{
    protected $table = 'school_admission_transactions';

    protected $fillable = ['user_id','product_id','wallet_account_id','reference','idempotency_key','provider_reference','candidate_identifier','customer_data','status','amount_minor','currency','provider_data','error','requery_required','next_requery_at','processed_at'];

    protected function casts(): array
    {
        return ['customer_data' => 'array','provider_data' => 'array','requery_required' => 'boolean','amount_minor' => 'integer','next_requery_at' => 'datetime','processed_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(SchoolAdmissionProduct::class, 'product_id');
    }
}