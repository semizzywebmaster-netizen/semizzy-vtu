<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
            'user' => $this->profilePayload($user),
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'email' => ['required','email','max:255',Rule::unique('users','email')->ignore($user->id)],
            'phone' => ['nullable','string','max:30'],
            'avatar' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:5120'],
            'address' => ['nullable','string','max:1000'],
            'city' => ['nullable','string','max:100'],
            'state' => ['nullable','string','max:100'],
            'country' => ['nullable','string','max:100'],
            'postal_code' => ['nullable','string','max:30'],
            'date_of_birth' => ['nullable','date','before:today'],
            'gender' => ['nullable',Rule::in(['male','female','non_binary','prefer_not_to_say'])],
            'occupation' => ['nullable','string','max:120'],
            'identity_type' => ['nullable',Rule::in(['nin','bvn','passport','drivers_license','voters_card','other'])],
            'identity_number' => ['nullable','string','min:4','max:120'],
            'identity_document' => ['nullable','file','mimes:jpg,jpeg,png,pdf,webp','max:10240'],
        ]);

        // Confidential identity fields are intentionally write-once.
        if (filled($user->phone)) {
            if (array_key_exists('phone', $data) && preg_replace('/[^0-9+]/', '', (string) $data['phone']) !== preg_replace('/[^0-9+]/', '', (string) $user->phone)) {
                return back()->withErrors(['phone' => 'Your phone number is confidential and cannot be edited after it has been saved.'])->withInput();
            }
            unset($data['phone']);
        }
        if (filled($user->identity_number)) {
            if (array_key_exists('identity_number', $data) && trim((string) $data['identity_number']) !== trim((string) $user->identity_number)) {
                return back()->withErrors(['identity_number' => 'Your identity number is confidential and cannot be edited after it has been saved.'])->withInput();
            }
            unset($data['identity_number']);
        }
        unset($data['name'], $data['username']);
        if (array_key_exists('phone', $data)) {
            $data['phone'] = filled($data['phone']) ? preg_replace('/[^0-9+]/', '', (string) $data['phone']) : null;
        }

        $emailChanged = strcasecmp((string) $user->email, (string) $data['email']) !== 0;
        $identityNumberAdded = ! filled($user->identity_number) && filled($data['identity_number'] ?? null);

        try {
            $payload = [
                'email' => strtolower(trim($data['email'])),
                'phone' => array_key_exists('phone', $data) ? $data['phone'] : $user->phone,
                'address' => filled($data['address'] ?? null) ? trim($data['address']) : null,
                'city' => filled($data['city'] ?? null) ? trim($data['city']) : null,
                'state' => filled($data['state'] ?? null) ? trim($data['state']) : null,
                'country' => filled($data['country'] ?? null) ? trim($data['country']) : null,
                'postal_code' => filled($data['postal_code'] ?? null) ? trim($data['postal_code']) : null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'occupation' => filled($data['occupation'] ?? null) ? trim($data['occupation']) : null,
                'identity_type' => $data['identity_type'] ?? $user->identity_type,
            ];

            if ($identityNumberAdded) {
                $payload['identity_number'] = trim((string) $data['identity_number']);
                $payload['kyc_status'] = 'pending';
                $payload['kyc_submitted_at'] = now();
                $payload['kyc_reviewed_at'] = null;
                $payload['kyc_rejection_reason'] = null;
            }

            if ($emailChanged) {
                $payload['email_verified_at'] = null;
            }

            if ($request->hasFile('avatar')) {
                $old = $user->avatar_path;
                $payload['avatar_path'] = $request->file('avatar')->store('users/avatars', 'public');
                if ($old) Storage::disk('public')->delete($old);
            }

            if ($request->hasFile('identity_document')) {
                $old = $user->identity_document_path;
                $payload['identity_document_path'] = $request->file('identity_document')->store('users/identity-documents', 'public');
                $payload['kyc_status'] = 'pending';
                $payload['kyc_submitted_at'] = now();
                $payload['kyc_reviewed_at'] = null;
                $payload['kyc_rejection_reason'] = null;
                if ($old) Storage::disk('public')->delete($old);
            }

            $user->forceFill($payload)->saveOrFail();

            try {
                $audit->record('profile.updated', $user->fresh(), [
                    'profile_fields_changed' => array_values(array_diff(array_keys($payload), ['avatar_path','identity_document_path','identity_number'])),
                    'email_changed' => $emailChanged,
                    'avatar_changed' => isset($payload['avatar_path']),
                    'identity_document_changed' => isset($payload['identity_document_path']),
                    'kyc_submitted' => $identityNumberAdded || isset($payload['identity_document_path']),
                ], $request);
            } catch (\Throwable $auditException) {
                report($auditException);
            }

            return back()->with('success', 'Your profile has been updated.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Your profile could not be updated safely.');
        }
    }

    private function profilePayload(User $user): array
    {
        return [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatarUrl' => $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : null,
            'address' => $user->address,
            'city' => $user->city,
            'state' => $user->state,
            'country' => $user->country,
            'postalCode' => $user->postal_code,
            'dateOfBirth' => $user->date_of_birth?->format('Y-m-d'),
            'gender' => $user->gender,
            'occupation' => $user->occupation,
            'identityType' => $user->identity_type,
            'identityNumber' => $user->identity_number,
            'identityDocumentUrl' => $user->identity_document_path ? Storage::disk('public')->url($user->identity_document_path) : null,
            'kycStatus' => $user->kyc_status ?? 'not_started',
            'emailVerifiedAt' => $user->email_verified_at?->toISOString(),
            'phoneVerifiedAt' => $user->phone_verified_at?->toISOString(),
            'role' => $user->role,
            'status' => $user->status,
            'referralCode' => $user->referral_code,
            'referralLink' => url('/register?ref=' . urlencode((string) $user->referral_code)),
        ];
    }
}
