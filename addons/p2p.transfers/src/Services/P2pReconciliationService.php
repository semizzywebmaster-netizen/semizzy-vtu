<?php

namespace Semizzy\Addons\P2p\Services;

use App\Models\WalletMovement;
use Semizzy\Addons\P2p\Models\P2pTradeOffer;
use Semizzy\Addons\Escrow\Models\EscrowTransaction;

final class P2pReconciliationService
{
    /**
     * Read-only audit. No wallet, escrow, listing, or offer state is mutated.
     */
    public function auditOffer(P2pTradeOffer $offer): array
    {
        $offer->loadMissing('listing', 'escrowTransaction');

        $issues = [];
        $escrow = $offer->escrowTransaction;

        if ($offer->status === 'accepted' && !$escrow) {
            $issues[] = [
                'code' => 'accepted_without_escrow',
                'message' => 'Accepted offer has no linked escrow transaction.',
            ];
        }

        if ($escrow) {
            if ((int) $escrow->buyer_id !== (int) $offer->buyer_id) {
                $issues[] = [
                    'code' => 'escrow_buyer_mismatch',
                    'message' => 'Escrow buyer does not match the offer buyer.',
                ];
            }

            if ((int) $escrow->seller_id !== (int) $offer->seller_id) {
                $issues[] = [
                    'code' => 'escrow_seller_mismatch',
                    'message' => 'Escrow seller does not match the offer seller.',
                ];
            }

            if ((string) $escrow->amount_minor !== (string) $offer->price_minor) {
                $issues[] = [
                    'code' => 'escrow_amount_mismatch',
                    'message' => 'Escrow amount does not match the accepted offer price.',
                ];
            }

            if ((string) $escrow->idempotency_key !== 'p2p-offer:' . $offer->id) {
                $issues[] = [
                    'code' => 'escrow_idempotency_mismatch',
                    'message' => 'Escrow idempotency key does not match the P2P offer.',
                ];
            }

            $wallet = \App\Models\WalletAccount::query()
                ->where('user_id', $escrow->buyer_id)
                ->where('currency', strtoupper((string) $escrow->currency))
                ->first();

            if (!$wallet) {
                $issues[] = [
                    'code' => 'missing_buyer_wallet',
                    'message' => 'The escrow buyer wallet could not be found.',
                ];
            } else {
                $movements = WalletMovement::query()
                    ->where('wallet_account_id', $wallet->id)
                    ->whereIn('operation_key', [
                        'escrow:hold:' . $escrow->id,
                        'escrow:cancel:release:' . $escrow->id,
                        'escrow:expire:release:' . $escrow->id,
                        'escrow:resolve:refund:' . $escrow->id,
                    ])
                    ->get();

                if ($escrow->status === 'funded' && $movements->where('operation_key', 'escrow:hold:' . $escrow->id)->count() !== 1) {
                    $issues[] = [
                        'code' => 'funded_hold_mismatch',
                        'message' => 'Funded escrow should have exactly one buyer hold movement.',
                    ];
                }

                $terminalReleaseCount = $movements
                    ->whereIn('operation_key', [
                        'escrow:cancel:release:' . $escrow->id,
                        'escrow:expire:release:' . $escrow->id,
                        'escrow:resolve:refund:' . $escrow->id,
                    ])->count();

                if (in_array($escrow->status, ['cancelled', 'refunded'], true) && $terminalReleaseCount !== 1) {
                    $issues[] = [
                        'code' => 'refund_release_mismatch',
                        'message' => 'Cancelled/refunded escrow should have exactly one buyer hold-release movement.',
                    ];
                }

                if (count($movements) !== count($movements->unique('operation_key'))) {
                    $issues[] = [
                        'code' => 'duplicate_escrow_movement',
                        'message' => 'Duplicate escrow wallet movement keys were detected.',
                    ];
                }
            }
        }

        return [
            'offer_id' => (int) $offer->id,
            'reference' => (string) $offer->reference,
            'status' => (string) $offer->status,
            'escrow_id' => $escrow?->id,
            'escrow_status' => $escrow?->status,
            'healthy' => $issues === [],
            'issue_count' => count($issues),
            'issues' => $issues,
        ];
    }
}
