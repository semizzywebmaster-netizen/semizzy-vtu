<?php

namespace App\Http\Controllers;

use App\Models\WalletAccount;
use App\Models\WalletMovement;
use App\Services\System\SystemSettingsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionReceiptController extends Controller
{
    public function show(Request $request, WalletMovement $movement, SystemSettingsService $settings): Response
    {
        $user = $request->user();
        $wallet = WalletAccount::query()->where('user_id', $user->id)->whereKey($movement->wallet_account_id)->first();

        abort_unless($wallet, 404);

        return Inertia::render('TransactionReceipt', [
            'transaction' => [
                'id' => $movement->id,
                'reference' => $movement->reference,
                'type' => $movement->type,
                'amountMinor' => (string) $movement->amount_minor,
                'currency' => $movement->currency,
                'availableAfterMinor' => (string) $movement->available_after_minor,
                'createdAt' => $movement->created_at?->toISOString(),
                'metadata' => $movement->metadata,
            ],
            'platform' => $settings->all(),
        ]);
    }
}
