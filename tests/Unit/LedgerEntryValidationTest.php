<?php

namespace Tests\Unit;

use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class LedgerEntryValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_posted_transaction_rejects_direct_entry_creation(): void
    {
        $transaction = LedgerTransaction::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'reference' => 'posted-direct-entry-test',
            'type' => 'test',
            'status' => 'posted',
            'currency' => 'NGN',
        ]);

        $this->expectException(LogicException::class);

        $transaction->entries()->create([
            'ledger_account_id' => $this->createLedgerAccount(),
            'debit_minor' => '100',
            'credit_minor' => '0',
        ]);
    }

    public function test_pending_entry_requires_exactly_one_side(): void
    {
        $transaction = LedgerTransaction::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'reference' => 'pending-entry-validation-test',
            'type' => 'test',
            'status' => 'pending',
            'currency' => 'NGN',
        ]);

        $this->expectException(RuntimeException::class);

        $transaction->entries()->create([
            'ledger_account_id' => $this->createLedgerAccount(),
            'debit_minor' => '0',
            'credit_minor' => '0',
        ]);
    }

    private function createLedgerAccount(): int
    {
        $now = now();

        return (int) DB::table('ledger_accounts')->insertGetId([
            'code' => 'TEST-'.uniqid(),
            'name' => 'Test Ledger',
            'type' => 'asset',
            'currency' => 'NGN',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
