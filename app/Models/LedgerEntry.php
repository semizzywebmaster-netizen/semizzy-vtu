<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class LedgerEntry extends Model
{
    protected $fillable = ['ledger_transaction_id', 'ledger_account_id', 'debit_minor', 'credit_minor'];

    protected function casts(): array
    {
        return ['debit_minor' => 'string', 'credit_minor' => 'string'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'ledger_transaction_id');
    }

    protected static function booted(): void
    {
        static::updating(function (self $entry): void {
            if ($entry->transaction()->value('status') === 'posted') {
                throw new LogicException('Entries belonging to posted ledger transactions are immutable.');
            }
        });

        static::deleting(function (self $entry): void {
            if ($entry->transaction()->value('status') === 'posted') {
                throw new LogicException('Entries belonging to posted ledger transactions cannot be deleted.');
            }
        });
    }
}
