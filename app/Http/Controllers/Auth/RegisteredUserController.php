<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response { return Inertia::render('Auth/Register'); }

    public function store(Request $request): RedirectResponse
    {
        $phone = preg_replace('/[^0-9+]/', '', (string) $request->input('phone'));

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => ['required','confirmed',Rules\Password::defaults()],
            'terms' => 'accepted',
        ]);
        $request->merge(['phone' => $phone]);
        $request->validate(['phone' => 'required|string|max:30|unique:users,phone']);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $phone,
            'password' => Hash::make($data['password']),
            'role' => 'USER',
            'status' => 'active',
        ]);

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice')->with('success', 'Account created successfully. Please verify your email before continuing.');
    }
}
