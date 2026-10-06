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
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Register', [
            'referral' => trim((string) $request->query('ref')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
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

        $user = User::create([
            'name' => $data['name'],
            'username' => $username,
            'email' => $data['email'],
            'phone' => $phone,
            'password' => Hash::make($data['password']),
            'role' => 'USER',
            'status' => 'active',
            'referred_by_id' => $referrer?->id,
            'referral_code' => $this->makeReferralCode($username),
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
