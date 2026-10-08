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
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordRecoveryController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function requestOtp(Request $request, OtpChallengeService $otp, SecurityEventLogger $events): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'otp_channel' => ['required', 'in:email,sms,whatsapp']]);
        $user = User::query()->where('email', $data['email'])->first();

        if ($user) {
            try {
                $otp->sendToUser($user, 'password_forgot', 'password recovery', $data['otp_channel']);

            } catch (\Throwable $e) {
                report($e);
            }
        }

        $events->record('auth.password_recovery.otp_requested', 'info', ['account_found' => (bool) $user], $request);

        return back()->with('otp_sent', 'If an account exists for that email, a 6-digit verification code has been sent. It expires in 10 minutes.');
    }

    public function reset(Request $request, OtpChallengeService $otp, CredentialHistoryService $history, SecurityEventLogger $events): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp_code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages(['otp_code' => 'The verification code is invalid or expired.']);
        }

        $otp->verifyForUser($user, 'password_forgot', $data['otp_code']);
        $history->assertPasswordIsFresh($user, $data['password']);
        $oldHash = (string) $user->password;

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'remember_token' => bin2hex(random_bytes(30)),
        ])->saveOrFail();

        $history->recordPassword($user, $oldHash);
        $user->tokens()->delete();

        $events->record('auth.password_recovery.completed', 'info', [
            'api_tokens_revoked' => true,
            'otp_verified' => true,
        ], $request);

        return redirect()->route('login')->with('success', 'Your password has been reset. You can now sign in.');
    }
}