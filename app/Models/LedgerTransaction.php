<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\HasMany;

class LedgerTransaction extends Model
{
    protected $fillable = ['uuid','reference','type','status','currency','description','metadata'];

    protected function casts(): array { return ['metadata' => 'array']; }

    public function entries(): HasMany { return $this->hasMany(LedgerEntry::class); }

    public function isBalanced(): bool
    {
        $debit = $this->entries()->sum('debit_minor');
        $credit = $this->entries()->sum('credit_minor');
        return (string) $debit === (string) $credit;
    }
}
