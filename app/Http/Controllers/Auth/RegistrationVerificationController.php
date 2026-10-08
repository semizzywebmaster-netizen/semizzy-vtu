<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Security\OtpChallengeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationVerificationController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) return redirect()->route('register');

        $channels = $this->channels();
        return Inertia::render('Auth/VerifyRegistration', [
            'channels' => $channels,
            'selectedChannel' => session('registration_channel', $channels[0] ?? 'email'),
            'destination' => $this->mask($user, session('registration_channel', $channels[0] ?? 'email')),
            'expiryMinutes' => (int) (SystemSetting::query()->where('key','registration_otp_expiry_minutes')->value('value') ?? 10),
            'resendSeconds' => (int) (SystemSetting::query()->where('key','registration_otp_resend_seconds')->value('value') ?? 60),
        ]);
    }

    public function send(Request $request, OtpChallengeService $otp): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) return redirect()->route('register');

        $channels = $this->channels();
        $data = $request->validate(['channel'=>'required|in:'.implode(',', $channels)]);
        $expiry = (int) (SystemSetting::query()->where('key','registration_otp_expiry_minutes')->value('value') ?? 10);
        $attempts = (int) (SystemSetting::query()->where('key','registration_otp_max_attempts')->value('value') ?? 5);
        $otp->sendRegistration($user, $data['channel'], $expiry, $attempts);
        $request->session()->put('registration_channel', $data['channel']);
        return back()->with('success','A new verification code has been sent.');
    }

    public function verify(Request $request, OtpChallengeService $otp): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) return redirect()->route('register');

        $channel = session('registration_channel', 'email');
        $request->validate(['otp_code'=>'required|digits:6']);
        try {
            $otp->verifyForUser($user, 'registration', (string) $request->input('otp_code'));
        } catch (ValidationException $e) {
            throw $e;
        }

        $user->forceFill([
            'status'=>'active',
            'email_verified_at'=>$user->email_verified_at ?: now(),
            'phone_verified_at'=>$channel !== 'email' ? ($user->phone_verified_at ?: now()) : $user->phone_verified_at,
        ])->saveOrFail();

        $deviceCookie = (string) session('pending_registration_device_cookie', $request->cookie('semizzy_device_key',''));
        $deviceKey = hash('sha256', $deviceCookie);
        Auth::login($user);
        $request->session()->forget(['pending_registration_user_id','pending_registration_device_cookie','registration_channel']);
        $request->session()->regenerate();
        if (! $user->devices()->where('device_key',$deviceKey)->exists()) {
            $user->devices()->create(['device_key'=>$deviceKey,'name'=>substr((string)$request->userAgent(),0,190),'ip_address'=>$request->ip(),'user_agent'=>substr((string)$request->userAgent(),0,500),'last_seen_at'=>now(),'authenticated_at'=>now(),'auth_method'=>'registration']);
        }
        return redirect()->route('dashboard')->with('success','Registration verified successfully. Welcome to the platform.');
    }

    private function pendingUser(Request $request): ?User
    {
        $id = (int) $request->session()->get('pending_registration_user_id', 0);
        return $id > 0 ? User::query()->find($id) : null;
    }

    private function channels(): array
    {
        $channels = json_decode((string) (SystemSetting::query()->where('key','registration_otp_channels')->value('value') ?? '[\"email\"]'), true) ?: ['email'];
        return array_values(array_intersect($channels, ['email','sms','whatsapp']));
    }

    private function mask(User $user, string $channel): string
    {
        if ($channel === 'email') {
            $parts = explode('@', (string)$user->email, 2);
            $local = $parts[0] ?? '';
            return (strlen($local) > 2 ? substr($local,0,2).'***' : '***').'@'.($parts[1] ?? '');
        }
        $phone = preg_replace('/\D+/', '', (string)$user->phone);
        return $phone ? str_repeat('*', max(0, strlen($phone)-4)).substr($phone,-4) : 'your phone';
    }
}
