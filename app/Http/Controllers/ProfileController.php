<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        $tierNumber = max(1, min(4, (int) $user->tier));
        $tiers = collect(config('semizzy.user_tiers', []))->map(fn (array $definition, $key) => [
            'id' => (int) $key,
            'name' => $definition['name'],
            'requirements' => $definition['requirements'] ?? [],
            'upgradeLabel' => $definition['upgrade_label'] ?? null,
            'current' => (int) $key === $tierNumber,
        ])->values()->all();

        return Inertia::render('Profile', [
            'tier' => $tierNumber,
            'tiers' => $tiers,
            'user' => [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'status' => $user->status,
                'emailVerifiedAt' => $user->email_verified_at?->toISOString(),
                'phoneVerifiedAt' => $user->phone_verified_at?->toISOString(),
                'referralCode' => $user->referral_code,
                'referralLink' => url('/register?ref=' . urlencode((string) $user->referral_code)),
            ],
        ]);
    }
}
