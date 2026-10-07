<?php

namespace App\Services\Vtu;

use App\Models\FinancialOperation;
use App\Models\VtuBulkOperation;
use App\Models\VtuBulkOperationItem;
use App\Models\VtuTransaction;
use App\Models\WalletAccount;
use App\Models\WalletMovement;

class VtuBulkReconciliationService
{
    /**
     * Read-only reconciliation. This service never changes financial state.
     */
    public function audit(VtuBulkOperation $bulk): array
    {
        $bulk->loadMissing('items');

        $items = [];
        $issues = [];

        foreach ($bulk->items as $item) {
            $result = $this->auditItem($item);
            $items[] = $result;

            foreach ($result['issues'] as $issue) {
                $issues[] = [
                    'item_id' => $item->id,
                    'sequence' => $item->sequence,
                    'code' => $issue['code'],
                    'message' => $issue['message'],
                ];
            }
        }

        return [
            'bulk_operation_id' => (int) $bulk->id,
            'reference' => (string) $bulk->reference,
            'status' => (string) $bulk->status,
            'total_items' => (int) $bulk->total_items,
            'audited_items' => count($items),
            'issue_count' => count($issues),
            'healthy' => $issues === [],
            'items' => $items,
            'issues' => $issues,
        ];
    }

    private function auditItem(VtuBulkOperationItem $item): array
    {
        $issues = [];
        $transaction = $item->transaction;

        if (!$transaction) {
            $issues[] = [
                'code' => 'missing_transaction',
                'message' => 'Bulk item has no linked VTU transaction.',
            ];

            return $this->result($item, null, null, null, $issues);
        }

        if ((int) $transaction->user_id !== (int) $item->bulk->user_id) {
            $issues[] = [
                'code' => 'user_mismatch',
                'message' => 'Linked VTU transaction belongs to a different user.',
            ];
        }

        if ((string) $transaction->idempotency_key !== (string) $item->idempotency_key) {
            $issues[] = [
                'code' => 'idempotency_mismatch',
                'message' => 'Bulk item and VTU transaction idempotency keys do not match.',
            ];
        }

        if ((int) $transaction->service_product_id !== (int) $item->product_id) {
            $issues[] = [
                'code' => 'product_mismatch',
                'message' => 'Bulk item and VTU transaction product IDs do not match.',
            ];
        }

        if ((string) $item->amount_minor !== '' && (string) $item->amount_minor !== (string) $transaction->total_minor) {
            $issues[] = [
                'code' => 'amount_mismatch',
                'message' => 'Bulk item amount does not match the VTU transaction total.',
            ];
        }

        $operation = $transaction->financialOperation;
        if (!$operation) {
            $issues[] = [
                'code' => 'missing_financial_operation',
                'message' => 'VTU transaction has no linked financial operation.',
            ];
        } else {
            if ((int) $operation->user_id !== (int) $transaction->user_id ||
                (string) $operation->amount_minor !== (string) $transaction->total_minor ||
                strtoupper((string) $operation->currency) !== strtoupper((string) $transaction->currency)) {
                $issues[] = [
                    'code' => 'financial_operation_mismatch',
                    'message' => 'Financial operation does not match the VTU transaction user, amount, or currency.',
                ];
            }
        }

        $wallet = WalletAccount::query()
            ->where('user_id', $transaction->user_id)
            ->where('currency', strtoupper((string) $transaction->currency))
            ->first();

        if (!$wallet) {
            $issues[] = [
                'code' => 'missing_wallet',
                'message' => 'No matching user wallet exists for the transaction currency.',
            ];

            return $this->result($item, $transaction, $operation, null, $issues);
        }

        $movements = WalletMovement::query()
            ->where('wallet_account_id', $wallet->id)
            ->whereIn('operation_key', [
                'vtu:' . $transaction->id . ':reserve',
                'vtu:' . $transaction->id . ':settle:success',
                'vtu:' . $transaction->id . ':settle:failure',
                'vtu:' . $transaction->id . ':refund',
            ])
            ->orderBy('id')
            ->get();

        $reserveCount = $movements->where('operation_key', 'vtu:' . $transaction->id . ':reserve')->count();
        $successCount = $movements->where('operation_key', 'vtu:' . $transaction->id . ':settle:success')->count();
        $failureCount = $movements->where('operation_key', 'vtu:' . $transaction->id . ':settle:failure')->count();
        $refundCount = $movements->where('operation_key', 'vtu:' . $transaction->id . ':refund')->count();

        if ($reserveCount > 1 || $successCount > 1 || $failureCount > 1 || $refundCount > 1) {
            $issues[] = [
                'code' => 'duplicate_wallet_movement',
                'message' => 'More than one movement exists for at least one idempotent VTU wallet operation.',
            ];
        }

        if ($reserveCount !== 1) {
            $issues[] = [
                'code' => 'reserve_movement_count',
                'message' => 'Expected exactly one reserve movement for the VTU transaction.',
            ];
        }

        if (!$transaction->isTerminal()) {
            if ($successCount > 0 || $failureCount > 0 || $refundCount > 0) {
                $issues[] = [
                    'code' => 'nonterminal_with_terminal_wallet_movement',
                    'message' => 'Non-terminal transaction has a terminal wallet movement.',
                ];
            }
        } elseif ($transaction->status === 'successful') {
            if ($successCount !== 1 || $failureCount > 0) {
                $issues[] = [
                    'code' => 'successful_settlement_mismatch',
                    'message' => 'Successful transaction must have exactly one success settlement and no failure settlement.',
                ];
            }
        } elseif (in_array($transaction->status, ['failed', 'reversed', 'cancelled'], true)) {
            if ($failureCount !== 1 && $refundCount !== 1) {
                $issues[] = [
                    'code' => 'failed_settlement_mismatch',
                    'message' => 'Failed/reversed/cancelled transaction must have a single failure settlement or refund path.',
                ];
            }
        }

        return $this->result($item, $transaction, $operation, $wallet, $issues, [
            'reserve' => $reserveCount,
            'success_settlement' => $successCount,
            'failure_settlement' => $failureCount,
            'refund' => $refundCount,
        ]);
    }

    private function result(
        VtuBulkOperationItem $item,
        ?VtuTransaction $transaction,
        ?FinancialOperation $operation,
        ?WalletAccount $wallet,
        array $issues,
        array $movementCounts = [],
    ): array {
        return [
            'item_id' => (int) $item->id,
            'sequence' => (int) $item->sequence,
            'status' => (string) $item->status,
            'transaction_id' => $transaction?->id,
            'financial_operation_id' => $operation?->id,
            'wallet_account_id' => $wallet?->id,
            'movement_counts' => $movementCounts,
            'issues' => $issues,
            'healthy' => $issues === [],
        ];
    }
}
