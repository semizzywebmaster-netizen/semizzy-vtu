<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\SecurityEventLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function __construct(private readonly SecurityEventLogger $securityEvents)
    {
    }

    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink(['email' => $data['email']]);

        $this->securityEvents->record('auth.password_reset.requested', 'info', [
            'result' => $status === Password::RESET_LINK_SENT ? 'sent' : 'accepted',
        ], $request);

        return back()->with('success', 'If an account exists for that email, a password reset link has been sent.');
    }
}
