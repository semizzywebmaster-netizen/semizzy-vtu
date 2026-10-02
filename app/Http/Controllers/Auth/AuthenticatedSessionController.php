<?php

namespace App\\Http\\Controllers\\Auth;

use App\\Http\\Controllers\\Controller;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Auth;
use Illuminate\\Support\\Facades\\RateLimiter;
use Illuminate\\Validation\\ValidationException;
use Inertia\\Inertia;
use Inertia\\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response { return Inertia::render('Auth/Login'); }

    public function createAdmin(): Response { return Inertia::render('Auth/AdminLogin'); }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email'=>'required|email','password'=>'required|string']);
        $this->authenticate($request, $credentials, false);
        return redirect()->intended(route('dashboard'));
    }

    public function storeAdmin(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email'=>'required|email','password'=>'required|string']);
        $this->authenticate($request, $credentials, true);
        return redirect()->intended(route('dashboard'));
    }

    private function authenticate(Request $request, array $credentials, bool $admin): void
    {
        $key = 'login:'.strtolower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email'=>'Too many login attempts. Please try again later.']);
        }
        if (!Auth::attempt(array_merge($credentials, ['status'=>'active']))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email'=>'The provided credentials are invalid.']);
        }
        if ($admin && !$request->user()->hasRole(['ADMIN','STAFF','SUPPORT'])) {
            Auth::logout();
            throw ValidationException::withMessages(['email'=>'This account is not authorized for the admin area.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }
}
