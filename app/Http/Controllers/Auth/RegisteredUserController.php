<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use App\Services\Security\OtpChallengeService;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(Request $request): Response
    {
        $registrationEnabled = filter_var(SystemSetting::query()->where('key','registration_enabled')->value('value') ?? '1', FILTER_VALIDATE_BOOL);
        if (! $registrationEnabled) abort(403, 'Registration is currently disabled by the administrator.');
        $verificationEnabled = filter_var(SystemSetting::query()->where('key','registration_verification_enabled')->value('value') ?? '1', FILTER_VALIDATE_BOOL);
        $channels = json_decode((string) (SystemSetting::query()->where('key','registration_otp_channels')->value('value') ?? '["email"]'), true) ?: ['email'];
        return Inertia::render('Auth/Register', [
            'referral' => trim((string) $request->query('ref')),
            'registrationVerification' => ['enabled'=>$verificationEnabled,'channels'=>array_values(array_intersect($channels,['email','sms','whatsapp']))],
        ]);
    }

    public function store(Request $request, OtpChallengeService $otp): RedirectResponse
    {
        if (! filter_var(SystemSetting::query()->where('key','registration_enabled')->value('value') ?? '1', FILTER_VALIDATE_BOOL)) {
            return back()->withErrors(['registration'=>'Registration is currently disabled by the administrator.'])->withInput();
        }
        $verificationEnabled = filter_var(SystemSetting::query()->where('key','registration_verification_enabled')->value('value') ?? '1', FILTER_VALIDATE_BOOL);
        $allowedChannels = json_decode((string) (SystemSetting::query()->where('key','registration_otp_channels')->value('value') ?? '["email"]'), true) ?: ['email'];
        $selectedChannel = strtolower(trim((string) $request->input('verification_channel','email')));
        if ($verificationEnabled && ! in_array($selectedChannel, $allowedChannels, true)) return back()->withErrors(['verification_channel'=>'Please choose an enabled verification channel.'])->withInput();
        $phone = preg_replace('/[^0-9+]/', '', (string) $request->input('phone'));
        $username = strtolower(trim((string) $request->input('username')));
        $reserved = collect(config('semizzy.username_policy.reserved', []))->map(fn ($value) => strtolower((string) $value));
        $protected = collect(config('semizzy.username_policy.protected_terms', []))->map(fn ($value) => strtolower((string) $value));
        $referralInput = trim((string) $request->input('referral_code'));

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'username' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-zA-Z0-9._]+$/', 'unique:users,username'],
            'email' => 'required|email|max:190|unique:users,email',
            'password' => ['required', 'confirmed', Rules\Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()],
            'terms' => 'accepted',
            'verification_channel' => $verificationEnabled ? 'required|in:email,sms,whatsapp' : 'nullable|in:email,sms,whatsapp',
        ]);

        if ($reserved->contains($username) || $protected->contains(fn ($term) => $term !== '' && str_contains($username, $term))) {
            return back()->withErrors(['username' => 'That username is reserved or protected. Please choose another username.'])->withInput();
        }

        $request->merge([
            'username' => $username,
            'phone' => $phone,
        ]);

        $request->validate([
            'phone' => 'required|string|max:30|unique:users,phone',
        ]);

        $referrer = null;
        if ($referralInput !== '') {
            $referralCode = $referralInput;
            if (str_contains($referralInput, 'ref=')) {
                parse_str((string) parse_url($referralInput, PHP_URL_QUERY), $query);
                $referralCode = trim((string) ($query['ref'] ?? ''));
            }
            $referrer = User::query()->where('referral_code', $referralCode)->first();
            if (! $referrer) {
                return back()->withErrors(['referral_code' => 'The referral code or link is invalid.'])->withInput();
            }
        }

        $deviceCookie = (string) $request->cookie('semizzy_device_key', '');
        if ($deviceCookie === '') {
            $deviceCookie = Str::uuid()->toString();
        }
        $deviceKey = hash('sha256', $deviceCookie);
        $registeredUserCount = \App\Models\UserDevice::query()->where('device_key', $deviceKey)->distinct('user_id')->count('user_id');
        if ($registeredUserCount >= 2) {
            return back()->withErrors(['registration' => 'This device has reached the maximum of 2 registered user accounts.'])->withInput();
        }

        $user = User::create([
            'name' => $data['name'],
            'username' => $username,
            'email' => $data['email'],
            'phone' => $phone,
            'password' => Hash::make($data['password']),
            'role' => 'USER',
            'status' => $verificationEnabled ? 'pending_verification' : 'active',
            'referred_by_id' => $referrer?->id,
            'referral_code' => $this->makeReferralCode($username),
        ]);

        if ($verificationEnabled) {
            try {
                $expiry = (int) (SystemSetting::query()->where('key','registration_otp_expiry_minutes')->value('value') ?? 10);
                $attempts = (int) (SystemSetting::query()->where('key','registration_otp_max_attempts')->value('value') ?? 5);
                $otp->sendRegistration($user, $selectedChannel, $expiry, $attempts);
            } catch (\Throwable $e) {
                $user->delete();
                if ($e instanceof \Illuminate\Validation\ValidationException) throw $e;
                report($e);
                return back()->withErrors(['verification_channel'=>'Verification could not be sent. Please try again or choose another enabled channel.'])->withInput();
            }
            $request->session()->put('pending_registration_user_id', $user->id);
            $request->session()->put('registration_channel', $selectedChannel);
            $request->session()->put('registration_otp_sent_at', now());
            $request->session()->put('pending_registration_device_cookie', $deviceCookie);
            return redirect()->route('registration.verify')->withCookie(cookie('semizzy_device_key', $deviceCookie, 525600, null, null, true, true, false, 'lax'))->with('success','Account created. Enter the verification code sent to your selected channel.');
        }

        $user->update(['email_verified_at'=>$user->email_verified_at ?: now()]);
        Auth::login($user);
        $request->session()->regenerate();
        $user->devices()->create(['device_key'=>$deviceKey,'name'=>substr((string)$request->userAgent(),0,190),'ip_address'=>$request->ip(),'user_agent'=>substr((string)$request->userAgent(),0,500),'last_seen_at'=>now(),'authenticated_at'=>now(),'auth_method'=>'registration']);
        return redirect()->route('dashboard')->withCookie(cookie('semizzy_device_key', $deviceCookie, 525600, null, null, true, true, false, 'lax'))->with('success', 'Account created successfully.');
    }

    private function makeReferralCode(string $username): string
    {
        $base = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $username));
        $base = substr($base ?: 'SEM', 0, 20);
        $candidate = $base;
        $suffix = 1;

        while (User::query()->where('referral_code', $candidate)->exists()) {
            $candidate = substr($base, 0, 20 - strlen((string) $suffix)) . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
