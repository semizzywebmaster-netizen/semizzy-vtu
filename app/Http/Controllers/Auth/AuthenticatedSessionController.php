<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\SecurityEventLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly SecurityEventLogger $securityEvents)
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
            'remember' => 'nullable|boolean',
        ]);

        if (! filled($credentials['login'] ?? null) && filled($credentials['email'] ?? null)) {
            $credentials['login'] = $credentials['email'];
        }

        if (! filled($credentials['login'] ?? null)) {
            throw ValidationException::withMessages(['login' => 'The login field is required.']);
        }

        $this->authenticate($request, $credentials, false);

        return redirect()->intended(route('dashboard'));
    }

    public function storeAdmin(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => 'required|email','password' => 'required|string']);
        $this->authenticate($request, $credentials, true);

        return redirect()->intended(route('dashboard'));
    }

    private function authenticate(Request $request, array $credentials, bool $admin): void
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

        if (! $admin && $user->phone === preg_replace('/[^0-9+]/', '', $login) && ! $user->phone_verified_at) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages([$this->loginErrorKey($request) => 'This phone number is not verified. Please use your email or username, or verify your phone first.']);
        }

        Auth::login($user, (bool) ($credentials['remember'] ?? false));

        if ($admin && ! $request->user()->hasRole(['ADMIN','STAFF','SUPPORT'])) {
            $this->securityEvents->record('auth.admin_login.denied', 'warning', ['admin' => true], $request);
            Auth::logout();
            throw ValidationException::withMessages(['login' => 'This account is not authorized for the admin area.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $this->securityEvents->record($admin ? 'auth.admin_login.success' : 'auth.login.success', 'info', ['admin' => $admin], $request);
    }

    private function loginErrorKey(Request $request): string
    {
        return $request->has('email') && ! $request->has('login') ? 'email' : 'login';
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->securityEvents->record('auth.logout', 'info', [], $request);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
