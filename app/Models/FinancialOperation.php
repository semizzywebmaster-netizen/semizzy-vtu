<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use LogicException;

class FinancialOperation extends Model
{
    protected $fillable = ['uuid', 'reference', 'user_id', 'type', 'status', 'amount_minor', 'currency', 'idempotency_key', 'provider_reference', 'metadata'];

    protected function casts(): array
    {
        return ['amount_minor' => 'string', 'metadata' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $operation): void {
            self::validateFinancialFields($operation);
        });

        static::updating(function (self $operation): void {
            if (self::isTerminal($operation->getOriginal('status'))) {
                throw new LogicException('Terminal financial operations are immutable.');
            }

            foreach (['uuid', 'reference', 'user_id', 'type', 'amount_minor', 'currency', 'idempotency_key'] as $field) {
                if ($operation->isDirty($field)) {
                    throw new LogicException("Financial operation {$field} is immutable after creation.");
                }
            }

            self::validateFinancialFields($operation);
        });

        static::deleting(function (): never {
            throw new LogicException('Financial operations are immutable and cannot be deleted.');
        });
    }

    private static function validateFinancialFields(self $operation): void
    {
        $amount = ltrim((string) ($operation->amount_minor ?? ''), '0') ?: '0';
        $currency = strtoupper(trim((string) ($operation->currency ?? '')));
        $status = strtolower(trim((string) ($operation->status ?? '')));

        if (!preg_match('/^\d+$/', $amount)) {
            throw new InvalidArgumentException('Financial operation amount must be a non-negative integer minor-unit value.');
        }

        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Financial operation currency must be a three-letter ISO-style code.');
        }

        if (trim((string) $operation->reference) === '' || trim((string) $operation->type) === '') {
            throw new InvalidArgumentException('Financial operation reference and type are required.');
        }

        if (!in_array($status, ['pending', 'processing', 'completed', 'failed', 'cancelled', 'reversed'], true)) {
            throw new InvalidArgumentException('Financial operation status is invalid.');
        }

        if ($operation->idempotency_key !== null && trim((string) $operation->idempotency_key) === '') {
            throw new InvalidArgumentException('Idempotency key cannot be empty.');
        }

        $operation->amount_minor = $amount;
        $operation->currency = $currency;
        $operation->status = $status;
    }

    private static function isTerminal(?string $status): bool
    {
        return in_array($status, ['completed', 'failed', 'cancelled', 'reversed'], true);
    }
}
