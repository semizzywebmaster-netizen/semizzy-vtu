<?php

namespace App\Http\Controllers;

use App\Services\Security\OtpChallengeService;
use App\Services\Security\SecurityEventLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SecurityOtpController extends Controller
{
    public function request(Request $request, OtpChallengeService $otp, SecurityEventLogger $securityEvents): RedirectResponse
    {
        $data = $request->validate([
            'purpose' => ['required', 'in:transaction_pin_change,password_change'],
        ]);

        $user = $request->user();

        if (! filled($user->email) || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return back()->withErrors(['otp_code' => 'A valid email address is required before OTP verification can be used.']);
        }

        $label = $data['purpose'] === 'transaction_pin_change' ? 'transaction PIN change' : 'password change';

        try {
            $otp->send($user, $data['purpose'], $label);
            $securityEvents->record('auth.otp.sent', 'info', [
                'purpose' => $data['purpose'],
                'channel' => 'email',
            ], $request);

            return back()->with('otp_sent', 'A 6-digit verification code has been sent to your registered email address. It expires in 10 minutes.');
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['otp_code' => 'We could not send the verification code. Please try again.']);
        }
    }
}
