<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\SecurityEventLogger;
use App\Services\Security\OtpChallengeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly SecurityEventLogger $securityEvents, private readonly OtpChallengeService $otp)
    {
    }

    public function create(): Response { return Inertia::render('Auth/Login'); }

    public function createAdmin(): Response { return Inertia::render('Auth/AdminLogin'); }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => 'nullable|string|max:190',
            'email' => 'nullable|email|max:190',
            'password' => 'required|string',
            'pin' => 'nullable|digits:4',
            'remember' => 'nullable|boolean',
            'otp_code' => 'nullable|digits:6',
        ]);

        if (! filled($credentials['login'] ?? null) && filled($credentials['email'] ?? null)) {
            $credentials['login'] = $credentials['email'];
        }

        if (! filled($credentials['login'] ?? null)) {
            throw ValidationException::withMessages(['login' => 'The login field is required.']);
        }

        $deviceKey = $this->authenticate($request, $credentials, false);

        return redirect()->intended(route('dashboard'))->withCookie(cookie('semizzy_device_key', $deviceKey, 525600, null, null, true, true, false, 'lax'));
    }

    public function storeAdmin(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => 'required|email','password' => 'required|string','otp_code' => 'nullable|digits:6']);
        $deviceKey = $this->authenticate($request, $credentials, true);

        return redirect()->intended(route('dashboard'))->withCookie(cookie('semizzy_device_key', $deviceKey, 525600, null, null, true, true, false, 'lax'));
    }

    private function authenticate(Request $request, array $credentials, bool $admin): string
    {
        $login = strtolower(trim((string) ($credentials['login'] ?? $credentials['email'] ?? '')));
        $query = \App\Models\User::query()->where('status', 'active');

        if ($admin) {
            $query->where('email', $login);
        } else {
            $query->where(function ($q) use ($login): void {
                $q->whereRaw('LOWER(email) = ?', [$login])
                    ->orWhereRaw('LOWER(username) = ?', [$login])
                    ->orWhere('phone', preg_replace('/[^0-9+]/', '', $login));
            });
        }

        $user = $query->first();
        $key = 'login:' . hash('sha256', $login) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->securityEvents->record('auth.login.rate_limited', 'warning', ['admin' => $admin], $request);
            throw ValidationException::withMessages([$this->loginErrorKey($request) => 'Too many login attempts. Please try again later.']);
        }

        if (! $user || ! Auth::validate(['email' => $user->email, 'password' => (string) $credentials['password'], 'status' => 'active'])) {
            RateLimiter::hit($key, 60);
            $this->securityEvents->record('auth.login.failed', 'warning', ['admin' => $admin], $request);
            throw ValidationException::withMessages([$this->loginErrorKey($request) => 'The provided credentials are invalid.']);
        }

        if (! $admin && $user->phone === preg_replace('/[^0-9+]/', '', $login)) {
            if (! $user->phone_verified_at) {
                RateLimiter::hit($key, 60);
                throw ValidationException::withMessages([$this->loginErrorKey($request) => 'This phone number is not verified. Please use your email or username, or verify your phone first.']);
            }
            if (! $user->whatsapp_verified_at || ! $user->whatsapp_transaction_enabled) {
                RateLimiter::hit($key, 60);
                throw ValidationException::withMessages([$this->loginErrorKey($request) => 'This WhatsApp number is not connected and verified. Verify your WhatsApp number before using it to sign in.']);
            }
        }

        $rawDeviceKey = (string) $request->cookie('semizzy_device_key', '');
        $deviceKey = $rawDeviceKey !== '' ? hash('sha256', $rawDeviceKey) : hash('sha256', Str::uuid()->toString());
        $activeDevice = $user->devices()->whereNull('revoked_at')->latest('id')->first();
        if ($activeDevice && $activeDevice->device_key !== $deviceKey) {
            if (!$request->session()->get('device_login_pending') || (int) $request->session()->get('device_login_user_id') !== (int) $user->id || (bool) $request->session()->get('device_login_admin', false) !== $admin) {
                $this->otp->sendToUser($user, 'new_device_login', 'new device login');
                $request->session()->put(['device_login_pending' => true, 'device_login_user_id' => $user->id, 'device_login_admin' => $admin]);
                throw ValidationException::withMessages(['login' => 'A verification code was sent to your email. Enter it to approve this new device.']);
            }
            if (!filled($credentials['otp_code'] ?? null)) {
                throw ValidationException::withMessages(['otp_code' => 'Enter the verification code sent to your email.']);
            }
            $this->otp->verifyForUser($user, 'new_device_login', (string)$credentials['otp_code']);
            $request->session()->regenerate();
            $user->devices()->whereNull('revoked_at')->update(['revoked_at'=>now()]);
            $activeDevice = null;
            $request->session()->forget(['device_login_pending','device_login_user_id','device_login_admin']);
        }
        Auth::login($user, (bool) ($credentials['remember'] ?? false));
        $sessionToken = Str::random(64);
        $device = $activeDevice ?: $user->devices()->create([
            'device_key' => $deviceKey,
            'name' => substr((string)$request->userAgent(),0,190),
            'ip_address' => $request->ip(),
        ]);
        $device->forceFill([
            'last_seen_at' => now(),
            'authenticated_at' => now(),
            'auth_method' => 'password',
            'session_token_hash' => Hash::make($sessionToken),
        ])->saveOrFail();
        $request->session()->put('device_session_token', $sessionToken);
        $request->session()->put('device_key', $deviceKey);
        $request->session()->put('device_id', $device->id);

        if ($admin && ! $request->user()->hasRole(['ADMIN','STAFF','SUPPORT'])) {
            $this->securityEvents->record('auth.admin_login.denied', 'warning', ['admin' => true], $request);
            Auth::logout();
            throw ValidationException::withMessages(['login' => 'This account is not authorized for the admin area.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $this->securityEvents->record($admin ? 'auth.admin_login.success' : 'auth.login.success', 'info', ['admin' => $admin], $request);
        return $deviceKey;
    }

    private function loginErrorKey(Request $request): string
    {
        return $request->has('email') && ! $request->has('login') ? 'email' : 'login';
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($request->user() && $request->session()->get('device_id')) {
            $request->user()->devices()->whereKey($request->session()->get('device_id'))->update(['revoked_at' => now()]);
        }
        $this->securityEvents->record('auth.logout', 'info', [], $request);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
