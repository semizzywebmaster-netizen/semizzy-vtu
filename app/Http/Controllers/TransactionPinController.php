<?php

namespace App\Http\Controllers;

use App\Models\CredentialHistory;
use App\Services\Security\OtpChallengeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
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

    public function store(Request $request, OtpChallengeService $otp): RedirectResponse
    {
        $data = $request->validate([
            'pin' => ['required', 'digits:4'],
            'pin_confirmation' => ['required', 'same:pin'],
            'current_pin' => ['nullable', 'digits:4'],
            'otp_code' => ['required', 'digits:6'],
        ]);

        $user = $request->user();

        $otp->verify($user, 'transaction_pin_change', $data['otp_code']);

        if (filled($user->transaction_pin_hash)) {
            if (! filled($data['current_pin']) || ! Hash::check($data['current_pin'], (string) $user->transaction_pin_hash)) {
                throw ValidationException::withMessages([
                    'current_pin' => 'Enter your current transaction PIN to change it.',
                ]);
            }
        }

        $hadPin = filled($user->transaction_pin_hash);
        if ($hadPin && Hash::check($data['pin'], (string) $user->transaction_pin_hash)) {
            throw ValidationException::withMessages(['pin' => 'You cannot reuse your current transaction PIN. Choose a different PIN.']);
        }
        $recent = CredentialHistory::query()->where('user_id', $user->id)->where('credential_type', 'transaction_pin')->latest('id')->get();
        foreach ($recent as $history) {
            if (Hash::check($data['pin'], $history->credential_hash)) {
                throw ValidationException::withMessages(['pin' => 'You cannot reuse a previous transaction PIN. Choose a different PIN.']);
            }
        }
        if ($hadPin) {
            CredentialHistory::create(['user_id' => $user->id, 'credential_type' => 'transaction_pin', 'credential_hash' => $user->transaction_pin_hash]);
        }
        $user->forceFill(['transaction_pin_hash' => Hash::make($data['pin'])])->saveOrFail();
        CredentialHistory::query()->where('user_id', $user->id)->where('credential_type', 'transaction_pin')->latest('id')->skip(5)->take(PHP_INT_MAX)->delete();

        return back()->with('success', $hadPin
            ? 'Transaction PIN changed successfully after OTP verification.'
            : 'Transaction PIN set successfully after OTP verification.');
    }
}
