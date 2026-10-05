<?php

namespace App\Services\Realtime;

use App\Models\VtuTransaction;
use Illuminate\Http\Request;

class RealtimeUpdateService
{
    public function snapshot(Request $request): array
    {
        $user = $request->user();
        $wallet = $user->walletAccounts()->where('status', '!=', 'closed')->orderBy('id')->first();

        $transactions = VtuTransaction::query()
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->limit(20)
            ->get(['id','uuid','reference','status','amount_minor','fee_minor','total_minor','currency','provider_status','updated_at','completed_at'])
            ->map(fn ($tx) => [
                'id' => $tx->id,
                'uuid' => $tx->uuid,
                'reference' => $tx->reference,
                'status' => $tx->status,
                'amountMinor' => (string) $tx->amount_minor,
                'feeMinor' => (string) $tx->fee_minor,
                'totalMinor' => (string) $tx->total_minor,
                'currency' => $tx->currency,
                'providerStatus' => $tx->provider_status,
                'updatedAt' => $tx->updated_at?->toISOString(),
                'completedAt' => $tx->completed_at?->toISOString(),
                'terminal' => $tx->isTerminal(),
            ])->values();

        $latestActivity = max(
            $wallet?->updated_at?->getTimestamp() ?? 0,
            $transactions->max(fn ($tx) => $tx['updatedAt'] ? strtotime($tx['updatedAt']) : 0),
        );

        return [
            'serverTime' => now()->toISOString(),
            'version' => (string) $latestActivity,
            'wallet' => $wallet ? [
                'id' => $wallet->id,
                'currency' => $wallet->currency,
                'availableMinor' => (string) $wallet->available_minor,
                'heldMinor' => (string) $wallet->held_minor,
                'status' => $wallet->status,
                'updatedAt' => $wallet->updated_at?->toISOString(),
            ] : null,
            'transactions' => $transactions,
            'notifications' => [
                'unreadCount' => $user->unreadNotifications()->count(),
                'latestId' => $user->notifications()->latest()->value('id'),
            ],
        ];
    }
}