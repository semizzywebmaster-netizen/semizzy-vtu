<?php

namespace App\Http\Controllers;

use App\Models\WalletAccount;
use App\Models\WalletMovement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserTransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $wallet = WalletAccount::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', 'closed')
            ->first();

        $movements = $wallet
            ? WalletMovement::query()
                ->where('wallet_account_id', $wallet->id)
                ->latest('created_at')
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (WalletMovement $movement) => [
                    'id' => $movement->id,
                    'reference' => $movement->reference,
                    'type' => $movement->type,
                    'amountMinor' => (string) $movement->amount_minor,
                    'currency' => $movement->currency,
                    'availableAfterMinor' => (string) $movement->available_after_minor,
                    'createdAt' => $movement->created_at?->toISOString(),
                    'metadata' => $movement->metadata,
                ])->values()->all()
            : [];

        return Inertia::render('Transactions', [
            'transactions' => $movements,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }
}
