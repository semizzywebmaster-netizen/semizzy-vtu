<?php

namespace App\Http\Controllers;

use App\Models\CredentialHistory;
use App\Services\Security\CredentialHistoryService;
use App\Services\Security\OtpChallengeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TransactionPinController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('TransactionPin', [
            'hasPin' => filled($request->user()->transaction_pin_hash),
            'email' => $request->user()->email,
        ]);
    }

    public function store(Request $request, OtpChallengeService $otp, CredentialHistoryService $history): RedirectResponse
    {
        $data = $request->validate([
            'pin' => ['required', 'digits:4'],
            'pin_confirmation' => ['required', 'same:pin'],
            'current_pin' => ['nullable', 'digits:4'],
            'forgot_pin' => ['nullable', 'boolean'],
            'otp_code' => ['required', 'digits:6'],
        ]);

        $user = $request->user();

        $otp->verify($user, 'transaction_pin_change', $data['otp_code']);

        if (filled($user->transaction_pin_hash) && ! $request->boolean('forgot_pin')) {
            if (! filled($data['current_pin']) || ! Hash::check($data['current_pin'], (string) $user->transaction_pin_hash)) {
                throw ValidationException::withMessages([
                    'current_pin' => 'Enter your current transaction PIN to change it.',
                ]);
            }
        }

        $hadPin = filled($user->transaction_pin_hash);
        $history->assertPinIsFresh($user, $data['pin']);

        if ($hadPin) {
            CredentialHistory::create([
                'user_id' => $user->id,
                'credential_type' => 'transaction_pin',
                'credential_hash' => $user->transaction_pin_hash,
            ]);
        }

        $user->forceFill(['transaction_pin_hash' => Hash::make($data['pin'])])->saveOrFail();

        return back()->with('success', $hadPin
            ? 'Transaction PIN changed successfully after OTP verification.'
            : 'Transaction PIN set successfully after OTP verification.');
    }
}
