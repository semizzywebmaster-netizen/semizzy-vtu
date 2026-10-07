<?php

namespace Semizzy\Addons\P2p\Services;

use App\Models\WalletAccount;
use App\Models\WalletMovement;
use Semizzy\Addons\P2p\Models\P2pTransfer;

final class P2pTransferReconciliationService
{
    /**
     * Read-only audit. Never mutates transfer or wallet state.
     */
    public function audit(P2pTransfer $transfer): array
    {
        $issues = [];

        $sender = WalletAccount::query()
            ->where('user_id', $transfer->sender_id)
            ->where('currency', strtoupper((string) $transfer->currency))
            ->first();

        $recipient = WalletAccount::query()
            ->where('user_id', $transfer->recipient_id)
            ->where('currency', strtoupper((string) $transfer->currency))
            ->first();

        if (!$sender) {
            $issues[] = ['code' => 'missing_sender_wallet', 'message' => 'Sender wallet is missing.'];
        }

        if (!$recipient) {
            $issues[] = ['code' => 'missing_recipient_wallet', 'message' => 'Recipient wallet is missing.'];
        }

        if ($sender && $recipient) {
            $debit = WalletMovement::query()
                ->where('wallet_account_id', $sender->id)
                ->where('operation_key', 'p2p:debit:' . $transfer->id)
                ->get();

            $credit = WalletMovement::query()
                ->where('wallet_account_id', $recipient->id)
                ->where('operation_key', 'p2p:credit:' . $transfer->id)
                ->get();

            if ($debit->count() !== 1) {
                $issues[] = [
                    'code' => 'debit_movement_count',
                    'message' => 'Transfer must have exactly one sender debit movement.',
                ];
            }

            if ($credit->count() !== 1) {
                $issues[] = [
                    'code' => 'credit_movement_count',
                    'message' => 'Transfer must have exactly one recipient credit movement.',
                ];
            }

            if ($debit->count() === 1) {
                $expectedDebit = function_exists('bcadd')
                    ? bcadd((string) $transfer->amount_minor, (string) $transfer->fee_minor, 0)
                    : (string) ((int) $transfer->amount_minor + (int) $transfer->fee_minor);

                if ((string) $debit->first()->amount_minor !== $expectedDebit) {
                    $issues[] = [
                        'code' => 'debit_amount_mismatch',
                        'message' => 'Sender debit movement does not match amount plus fee.',
                    ];
                }
            }

            if ($credit->count() === 1 && (string) $credit->first()->amount_minor !== (string) $transfer->amount_minor) {
                $issues[] = [
                    'code' => 'credit_amount_mismatch',
                    'message' => 'Recipient credit movement does not match transfer amount.',
                ];
            }

            if ($debit->count() === 1 && $credit->count() === 1) {
                if ((string) $debit->first()->reference !== (string) $transfer->reference ||
                    (string) $credit->first()->reference !== (string) $transfer->reference) {
                    $issues[] = [
                        'code' => 'movement_reference_mismatch',
                        'message' => 'Wallet movement references do not match the transfer reference.',
                    ];
                }

                if ((string) $debit->first()->currency !== (string) $transfer->currency ||
                    (string) $credit->first()->currency !== (string) $transfer->currency) {
                    $issues[] = [
                        'code' => 'movement_currency_mismatch',
                        'message' => 'Wallet movement currency does not match the transfer currency.',
                    ];
                }
            }
        }

        if ((string) $transfer->status === 'completed' && $issues !== []) {
            $issues[] = [
                'code' => 'completed_transfer_reconciliation_failure',
                'message' => 'Transfer is marked completed but its wallet movements are inconsistent.',
            ];
        }

        return [
            'transfer_id' => (int) $transfer->id,
            'reference' => (string) $transfer->reference,
            'status' => (string) $transfer->status,
            'healthy' => $issues === [],
            'issue_count' => count($issues),
            'issues' => $issues,
        ];
    }
}
