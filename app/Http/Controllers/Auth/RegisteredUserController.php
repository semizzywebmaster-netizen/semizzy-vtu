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
        $data = $request->validate([
            'name'=>'required|string|max:120',
            'email'=>'required|email|max:190|unique:users,email',
            'password'=>['required','confirmed',Rules\Password::defaults()],
        ]);
        $user=User::create([
            'name'=>$data['name'],
            'email'=>$data['email'],
            'password'=>Hash::make($data['password']),
            'role'=>'USER',
            'status'=>'active',
        ]);
        $user->sendEmailVerificationNotification();
        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->route('dashboard')->with('success','Account created successfully.');
    }
}
