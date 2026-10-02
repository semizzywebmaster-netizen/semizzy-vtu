<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\HasMany;
use LogicException;

class LedgerTransaction extends Model
{
    protected $fillable=['uuid','reference','type','status','currency','description','metadata'];
    protected function casts(): array { return ['metadata'=>'array']; }
    public function entries(): HasMany { return $this->hasMany(LedgerEntry::class); }
    public function isBalanced(): bool
    {
        $debit=(string)$this->entries()->sum('debit_minor');
        $credit=(string)$this->entries()->sum('credit_minor');
        return $debit===$credit;
    }
    protected static function booted(): void
    {
        static::updating(function(self $model): void {
            if($model->getOriginal('status')==='posted') throw new LogicException('Posted ledger transactions are immutable.');
        });
        static::deleting(fn(): never=>throw new LogicException('Ledger transactions are immutable and cannot be deleted.'));
    }
}
