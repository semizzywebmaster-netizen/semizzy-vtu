<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;

class LedgerEntry extends Model
{
    protected $fillable = ['ledger_transaction_id','ledger_account_id','debit_minor','credit_minor'];

    protected function casts(): array { return ['debit_minor' => 'integer', 'credit_minor' => 'integer']; }

    public function transaction(): BelongsTo { return $this->belongsTo(LedgerTransaction::class, 'ledger_transaction_id'); }
}
