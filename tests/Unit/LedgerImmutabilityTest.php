<?php

namespace Tests\Unit;

use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class LedgerImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_posted_transaction_and_entries_cannot_be_modified_or_deleted(): void
    {
        $transaction = LedgerTransaction::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'reference' => 'ledger-immutable-test',
            'type' => 'test',
            'status' => 'posted',
            'currency' => 'NGN',
        ]);

        $entry = $transaction->entries()->create([
            'ledger_account_id' => $this->createLedgerAccount(),
            'debit_minor' => '100',
            'credit_minor' => '0',
        ]);

        $this->expectException(LogicException::class);
        $entry->update(['debit_minor' => '200']);
    }

    public function test_posted_entry_cannot_be_deleted(): void
    {
        $transaction = LedgerTransaction::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'reference' => 'ledger-delete-test',
            'type' => 'test',
            'status' => 'posted',
            'currency' => 'NGN',
        ]);

        $entry = $transaction->entries()->create([
            'ledger_account_id' => $this->createLedgerAccount(),
            'debit_minor' => '100',
            'credit_minor' => '0',
        ]);

        $this->expectException(LogicException::class);
        $entry->delete();
    }

    private function createLedgerAccount(): int
    {
        return \App\Models\LedgerAccount::create([
            'code' => 'TEST-'.uniqid(),
            'name' => 'Test Ledger',
            'type' => 'asset',
            'currency' => 'NGN',
            'status' => 'active',
        ])->id;
    }
}
