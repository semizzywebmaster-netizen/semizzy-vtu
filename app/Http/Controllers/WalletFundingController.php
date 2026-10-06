<?php

namespace App\Http\Controllers;

use App\Models\WalletAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletFundingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $wallet = WalletAccount::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', 'closed')
            ->first();

        return Inertia::render('WalletFunding', [
            'wallet' => $wallet ? [
                'availableMinor' => (string) $wallet->available_minor,
                'currency' => $wallet->currency,
                'status' => $wallet->status,
            ] : null,
            'methods' => [],
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }
}
