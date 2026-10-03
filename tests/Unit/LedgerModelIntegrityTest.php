<?php

namespace Tests\Unit;

use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class LedgerModelIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_normalizes_currency_and_rejects_invalid_status(): void
    {
        $tx = LedgerTransaction::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'ledger-model-1',
            'type' => 'test',
            'status' => 'pending',
            'currency' => 'ngn',
        ]);

        $this->assertSame('NGN', $tx->currency);

        $this->expectException(InvalidArgumentException::class);
        LedgerTransaction::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'ledger-model-2',
            'type' => 'test',
            'status' => 'bogus',
            'currency' => 'NGN',
        ]);
    }

    public function test_entry_update_cannot_create_invalid_two_sided_entry(): void
    {
        $tx = LedgerTransaction::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'ledger-model-3',
            'type' => 'test',
            'status' => 'pending',
            'currency' => 'NGN',
        ]);

        $entry = $tx->entries()->create([
            'ledger_account_id' => $this->createLedgerAccount(),
            'debit_minor' => '100',
            'credit_minor' => '0',
        ]);

        $entry->credit_minor = '50';

        $this->expectException(RuntimeException::class);
        $entry->save();
    }

    public function test_posted_transaction_entries_cannot_be_updated(): void
    {
        $tx = LedgerTransaction::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'ledger-model-4',
            'type' => 'test',
            'status' => 'posted',
            'currency' => 'NGN',
        ]);

        $this->expectException(LogicException::class);
        $tx->entries()->create([
            'ledger_account_id' => $this->createLedgerAccount(),
            'debit_minor' => '100',
            'credit_minor' => '0',
        ]);
    }

    private function createLedgerAccount(): int
    {
        return (int) \Illuminate\Support\Facades\DB::table('ledger_accounts')->insertGetId([
            'code' => 'TEST-'.Str::uuid(),
            'name' => 'Test Ledger',
            'type' => 'asset',
            'currency' => 'NGN',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
