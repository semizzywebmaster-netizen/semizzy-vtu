<?php

namespace Addons\WhatsAppBot\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OtpChallenge;
use App\Services\Security\OtpChallengeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppBotVerificationController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('WhatsAppBot/Verify', [
            'phone' => $user->phone,
            'verified' => (bool) $user->whatsapp_verified_at,
            'transactionsEnabled' => (bool) $user->whatsapp_transaction_enabled,
        ]);
    }

    public function send(Request $request, OtpChallengeService $otp): RedirectResponse
    {
        $user = $request->user();

        if (! $user->phone) {
            return back()->withErrors(['whatsapp' => 'A registered phone number is required.']);
        }

        $otp->sendToUser($user, 'whatsapp_link', 'WhatsApp verification', 'whatsapp');

        return back()->with('success', 'A verification code has been sent to your registered WhatsApp number.');
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'otp_code' => ['required', 'digits:6'],
        ]);

        $challenge = OtpChallenge::query()
            ->where('user_id', $user->id)
            ->where('purpose', 'whatsapp_link')
            ->where('channel', 'whatsapp')
            ->where('destination', (string) $user->phone)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (
            ! $challenge
            || $challenge->expires_at?->isPast()
            || $challenge->attempts >= $challenge->max_attempts
        ) {
            throw ValidationException::withMessages([
                'otp_code' => 'Your WhatsApp verification code is invalid or expired. Request a new code.',
            ]);
        }

        if (! Hash::check((string) $data['otp_code'], (string) $challenge->code_hash)) {
            $challenge->increment('attempts');

            throw ValidationException::withMessages([
                'otp_code' => 'The WhatsApp verification code is incorrect.',
            ]);
        }

        $challenge->forceFill(['consumed_at' => now()])->saveOrFail();

        $user->forceFill([
            'whatsapp_verified_at' => now(),
            'whatsapp_transaction_enabled' => true,
        ])->saveOrFail();

        return back()->with('success', 'WhatsApp number confirmed and verified. WhatsApp transactions are now enabled.');
    }
}
