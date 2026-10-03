<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use LogicException;

class LedgerTransaction extends Model
{
    protected $fillable = ['uuid', 'reference', 'type', 'status', 'currency', 'description', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function isBalanced(): bool
    {
        $debit = (string) $this->entries()->sum('debit_minor');
        $credit = (string) $this->entries()->sum('credit_minor');

        return $debit === $credit;
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            self::validateFields($model);
        });

        static::updating(function (self $model): void {
            if ($model->getOriginal('status') === 'posted') {
                throw new LogicException('Posted ledger transactions are immutable.');
            }

            self::validateFields($model);
        });

        static::deleting(fn (): never => throw new LogicException('Ledger transactions are immutable and cannot be deleted.'));
    }

    private static function validateFields(self $model): void
    {
        $currency = strtoupper(trim((string) ($model->currency ?? '')));

        if (trim((string) $model->reference) === '' || trim((string) $model->type) === '') {
            throw new InvalidArgumentException('Ledger transaction reference and type are required.');
        }

        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Ledger transaction currency must be a three-letter ISO-style code.');
        }

        if (!in_array($model->status, ['pending', 'posted', 'failed', 'reversed'], true)) {
            throw new InvalidArgumentException('Ledger transaction status is invalid.');
        }

        $model->currency = $currency;
    }
}
