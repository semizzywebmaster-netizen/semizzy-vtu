<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use RuntimeException;

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
        static::creating(function (self $entry): void {
            $entry->ensureMutableTransaction();
            $entry->validateEntryAmounts();
        });

        static::updating(function (self $entry): void {
            $entry->ensureMutableTransaction();
            $entry->validateEntryAmounts();
        });

        static::deleting(function (self $entry): void {
            if ($entry->transaction()->value('status') === 'posted') {
                throw new LogicException('Entries belonging to posted ledger transactions cannot be deleted.');
            }
        });
    }

    private function ensureMutableTransaction(): void
    {
        $status = $this->transaction()->value('status');

        if ($status === null) {
            throw new RuntimeException('Ledger entry requires a valid transaction.');
        }

        if ($status === 'posted') {
            throw new LogicException('Entries belonging to posted ledger transactions are immutable.');
        }
    }

    private function validateEntryAmounts(): void
    {
        $debit = ltrim((string) ($this->debit_minor ?? '0'), '0') ?: '0';
        $credit = ltrim((string) ($this->credit_minor ?? '0'), '0') ?: '0';

        if (!preg_match('/^\d+$/', $debit) || !preg_match('/^\d+$/', $credit)) {
            throw new RuntimeException('Ledger amounts must be non-negative integer minor units.');
        }

        if (($debit === '0') === ($credit === '0')) {
            throw new RuntimeException('Each ledger entry must contain exactly one side.');
        }

        $this->debit_minor = $debit;
        $this->credit_minor = $credit;
    }
}
