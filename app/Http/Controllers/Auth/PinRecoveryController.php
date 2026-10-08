<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\CredentialHistoryService;
use App\Services\Security\OtpChallengeService;
use App\Services\Security\SecurityEventLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PinRecoveryController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPin');
    }

    public function requestOtp(Request $request, OtpChallengeService $otp, SecurityEventLogger $events): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'otp_channel' => ['required', 'in:email,sms,whatsapp']]);
        $user = User::query()->where('email', $data['email'])->first();

        if ($user) {
            try {
                $otp->sendToUser($user, 'pin_forgot', 'transaction PIN recovery', $data['otp_channel']);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $events->record('auth.pin_recovery.otp_requested', 'info', ['account_found' => (bool) $user], $request);

        return back()->with('otp_sent', 'If an account exists for that email, a 6-digit verification code has been sent. It expires in 10 minutes.');
    }

    public function reset(Request $request, OtpChallengeService $otp, CredentialHistoryService $history, SecurityEventLogger $events): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp_code' => ['required', 'digits:6'],
            'pin' => ['required', 'confirmed', 'digits:4'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();
        if (! $user) {
            throw ValidationException::withMessages(['otp_code' => 'The verification code is invalid or expired.']);
        }

        $otp->verifyForUser($user, 'pin_forgot', $data['otp_code']);
        $history->assertPinIsFresh($user, $data['pin']);
        $oldHash = (string) $user->transaction_pin_hash;

        $user->forceFill([
            'transaction_pin_hash' => Hash::make($data['pin']),
        ])->saveOrFail();

        if ($oldHash !== '') {
            \App\Models\CredentialHistory::create([
                'user_id' => $user->id,
                'credential_type' => 'transaction_pin',
                'credential_hash' => $oldHash,
            ]);
        }

        $user->devices()->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $events->record('auth.pin_recovery.completed', 'info', ['otp_verified' => true], $request);

        return redirect()->route('login')->with('success', 'Your transaction PIN has been reset. Please sign in again.');
    }
}
