<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (Hash::check($data['current_password'], $user->password) === false) {
            abort(422);
        }

        $user->forceFill([
            'password' => $data['password'],
            'remember_token' => bin2hex(random_bytes(30)),
        ])->save();

        $this->securityEvents->record('password.changed', 'info', [
            'user_id' => $user->id,
        ], $request);

        return back()->with('success', 'Your password has been changed.');
    }
}
