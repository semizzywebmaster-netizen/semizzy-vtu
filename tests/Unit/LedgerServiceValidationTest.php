<?php

namespace Tests\Unit;

use App\Services\Finance\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class LedgerServiceValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_rejects_missing_ledger_account(): void
    {
        config(['semizzy.finance_enabled' => true]);

        $service = app(LedgerService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('active ledger account');

        $service->post('missing-account-test', 'test', 'NGN', [
            ['ledger_account_id' => 999999, 'debit_minor' => '100', 'credit_minor' => '0'],
            ['ledger_account_id' => 999998, 'debit_minor' => '0', 'credit_minor' => '100'],
        ]);
    }

    public function test_post_rejects_mismatched_account_currency(): void
    {
        config(['semizzy.finance_enabled' => true]);

        $ngn = $this->createLedgerAccount('NGN');
        $usd = $this->createLedgerAccount('USD');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('currency must match');

        app(LedgerService::class)->post('currency-mismatch-test', 'test', 'NGN', [
            ['ledger_account_id' => $ngn, 'debit_minor' => '100', 'credit_minor' => '0'],
            ['ledger_account_id' => $usd, 'debit_minor' => '0', 'credit_minor' => '100'],
        ]);
    }

    private function createLedgerAccount(string $currency): int
    {
        $now = now();

        return (int) DB::table('ledger_accounts')->insertGetId([
            'code' => 'TEST-'.uniqid(),
            'name' => 'Test Ledger',
            'type' => 'asset',
            'currency' => $currency,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
