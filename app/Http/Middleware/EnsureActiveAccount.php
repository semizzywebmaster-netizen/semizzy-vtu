<?php

namespace App\Http\Middleware;

use App\Services\Security\SecurityEventLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function __construct(private readonly SecurityEventLogger $securityEvents)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status !== 'active') {
            $this->securityEvents->record('auth.inactive_account.session_revoked', 'warning', [
                'account_status' => $user->status,
            ], $request);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Your account is not active. Contact support if you need assistance.']);
        }

        return $next($request);
    }
}
