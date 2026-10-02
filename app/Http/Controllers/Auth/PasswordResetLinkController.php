<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink(['email' => $data['email']]);

        // Never disclose whether an email address belongs to an account.
        // Laravel returns INVALID_USER for unknown addresses; that is treated
        // as a normal response so the endpoint cannot be used for enumeration.
        if ($status !== Password::RESET_LINK_SENT && $status !== Password::INVALID_USER) {
            return back()->with('success', 'If an account exists for that email, a password reset link has been sent.');
        }

        return back()->with('success', 'If an account exists for that email, a password reset link has been sent.');
    }
}
