<?php

namespace App\Services\Finance;

use App\Models\LedgerTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class LedgerService
{
    public function post(string $reference, string $type, string $currency, array $entries, ?string $description = null, array $metadata = []): LedgerTransaction
    {
        abort_unless(config('semizzy.finance_enabled'), 503, 'Finance core is disabled.');

        $reference = trim($reference);
        $type = trim($type);
        $currency = strtoupper(trim($currency));

        if ($reference === '' || $type === '') {
            throw new RuntimeException('Ledger reference and type are required.');
        }

        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new RuntimeException('Ledger currency must be a three-letter ISO-style code.');
        }

        return DB::transaction(function () use ($reference, $type, $currency, $entries, $description, $metadata) {
            $existing = LedgerTransaction::query()
                ->where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->type !== $type || strtoupper((string) $existing->currency) !== $currency) {
                    throw new RuntimeException('Ledger reference is already bound to a different transaction type or currency.');
                }

                if (!$existing->isBalanced()) {
                    throw new RuntimeException('Existing ledger transaction is unbalanced and requires reconciliation.');
                }

                return $existing->load('entries');
            }

            if (count($entries) < 2) {
                throw new RuntimeException('A ledger transaction requires at least two entries.');
            }

            $debit = '0';
            $credit = '0';

            foreach ($entries as $entry) {
                $d = ltrim((string) ($entry['debit_minor'] ?? '0'), '0') ?: '0';
                $c = ltrim((string) ($entry['credit_minor'] ?? '0'), '0') ?: '0';

                if (!preg_match('/^\d+$/', $d) || !preg_match('/^\d+$/', $c)) {
                    throw new RuntimeException('Ledger amounts must be non-negative integer minor units.');
                }
                if (($d === '0') === ($c === '0')) {
                    throw new RuntimeException('Each ledger entry must contain exactly one side.');
                }

                $ledgerAccountId = $entry['ledger_account_id'] ?? null;
                if (!is_numeric($ledgerAccountId) || (int) $ledgerAccountId < 1) {
                    throw new RuntimeException('Each ledger entry requires a valid ledger account.');
                }

                $account = DB::table('ledger_accounts')
                    ->where('id', (int) $ledgerAccountId)
                    ->where('status', 'active')
                    ->first(['id', 'currency']);

                if ($account === null) {
                    throw new RuntimeException('Each ledger entry requires an active ledger account.');
                }

                if (strtoupper((string) $account->currency) !== $currency) {
                    throw new RuntimeException('Ledger entry account currency must match the transaction currency.');
                }

                $debit = $this->add($debit, $d);
                $credit = $this->add($credit, $c);
            }

            if ($debit !== $credit) {
                throw new RuntimeException('Unbalanced ledger transaction.');
            }

            $tx = LedgerTransaction::create([
                'uuid' => (string) Str::uuid(),
                'reference' => $reference,
                'type' => $type,
                'status' => 'pending',
                'currency' => $currency,
                'description' => $description,
                'metadata' => $metadata,
            ]);

            foreach ($entries as $entry) {
                $tx->entries()->create([
                    'ledger_account_id' => (int) $entry['ledger_account_id'],
                    'debit_minor' => ltrim((string) ($entry['debit_minor'] ?? '0'), '0') ?: '0',
                    'credit_minor' => ltrim((string) ($entry['credit_minor'] ?? '0'), '0') ?: '0',
                ]);
            }

            if (!$tx->isBalanced()) {
                throw new RuntimeException('Ledger transaction failed final balance validation.');
            }

            $tx->status = 'posted';
            $tx->save();

            return $tx->load('entries');
        });
    }

    private function add(string $a, string $b): string
    {
        if (function_exists('bcadd')) {
            return bcadd($a, $b, 0);
        }

        $a = ltrim($a, '0') ?: '0';
        $b = ltrim($b, '0') ?: '0';
        $carry = 0;
        $out = '';

        for ($i = 0, $j = 0; $i < strlen($a) || $j < strlen($b); $i++, $j++) {
            $sum = ($i < strlen($a) ? ord($a[strlen($a) - 1 - $i]) - 48 : 0)
                + ($j < strlen($b) ? ord($b[strlen($b) - 1 - $j]) - 48 : 0)
                + $carry;
            $out = ($sum % 10) . $out;
            $carry = intdiv($sum, 10);
        }

        return $carry === 0 ? $out : $carry . $out;
    }
}
