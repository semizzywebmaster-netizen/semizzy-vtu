<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\CredentialHistory;
use App\Services\Security\OtpChallengeService;
use App\Services\Security\SecurityEventLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordController extends Controller
{
    public function __construct(private readonly SecurityEventLogger $securityEvents)
    {
    }

    public function store(Request $request, OtpChallengeService $otp): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()],
            'otp_code' => ['required', 'digits:6'],
        ]);

        if (Hash::check($data['current_password'], $user->password) === false) {
            abort(422);
        }

        $otp->verify($user, 'password_change', $data['otp_code']);

        $user->forceFill([
            'password' => $data['password'],
            'remember_token' => bin2hex(random_bytes(30)),
        ])->save();

        $user->tokens()->delete();

        $this->securityEvents->record('password.changed', 'info', [
            'user_id' => $user->id,
            'api_tokens_revoked' => true,
            'otp_verified' => true,
        ], $request);

        return back()->with('success', 'Your password has been changed after OTP verification.');
    }
}
