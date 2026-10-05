<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPermissionOverride;
use App\Models\WalletAccount;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\AdminWalletFundingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'in:ADMIN,STAFF,SUPPORT,USER'],
            'status' => ['nullable', 'in:active,suspended,disabled'],
            'tier' => ['nullable', 'integer', 'in:1,2,3,4'],
        ]);

        $users = User::query()
            ->select([
                'id', 'name', 'username', 'email', 'phone', 'role', 'status', 'tier',
                'email_verified_at', 'phone_verified_at', 'account_type', 'business_name', 'business_registration_number', 'business_type', 'business_address', 'business_state', 'business_country', 'merchant_verified_at', 'tier_upgrade_status', 'created_at',
            ])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', '%'.$search.'%')
                        ->orWhere('username', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['tier'] ?? null, fn ($query, string $tier) => $query->where('tier', (int) $tier))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'status' => $user->status,
                'tier' => max(1, min(4, (int) $user->tier)),
                'emailVerified' => $user->email_verified_at !== null,
                'phoneVerified' => $user->phone_verified_at !== null,
                'accountType' => $user->account_type,
                'businessName' => $user->business_name,
                'businessRegistrationNumber' => $user->business_registration_number,
                'businessType' => $user->business_type,
                'businessAddress' => $user->business_address,
                'businessState' => $user->business_state,
                'businessCountry' => $user->business_country,
                'merchantVerified' => $user->merchant_verified_at !== null,
                'tierUpgradeStatus' => $user->tier_upgrade_status,
                'createdAt' => $user->created_at?->toISOString(),
                'isSelf' => $user->id === $request->user()->id,
                'wallet' => ($wallet = WalletAccount::query()->where('user_id', $user->id)->where('status', '!=', 'closed')->first()) ? [
                    'id' => $wallet->id, 'availableMinor' => $wallet->available_minor, 'heldMinor' => $wallet->held_minor,
                    'currency' => $wallet->currency, 'status' => $wallet->status,
                ] : null,
                'permissions' => collect(config('semizzy.role_permissions.'.$user->role, []))->mapWithKeys(fn ($permission): array => [$permission => true])->merge(
                    UserPermissionOverride::query()->where('user_id', $user->id)->pluck('allowed', 'permission')->map(fn ($allowed): bool => (bool) $allowed)
                )->all(),
            ]);

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'filters' => $filters,
            'permissions' => collect(config('semizzy.role_permissions', []))->flatten()->unique()->sort()->values()->all(),
            'tiers' => collect(config('semizzy.user_tiers', []))->map(fn (array $tier, $key): array => [
                'id' => (int) $key,
                'name' => $tier['name'],
                'dailyLimitMinor' => $tier['daily_limit_minor'],
                'balanceLimitMinor' => $tier['balance_limit_minor'],
                'upgradeLabel' => $tier['upgrade_label'],
            ])->values()->all(),
        ]);
    }

    public function update(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-zA-Z0-9._]+$/', 'unique:users,username,'.$user->id],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'in:ADMIN,STAFF,SUPPORT,USER'],
            'status' => ['required', 'in:active,suspended,disabled'],
            'tier' => ['required', 'integer', 'in:1,2,3,4'],
            'account_type' => ['required', 'in:personal,merchant'],
            'business_name' => ['nullable', 'string', 'max:180'],
            'business_registration_number' => ['nullable', 'string', 'max:100'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'business_address' => ['nullable', 'string', 'max:500'],
            'business_state' => ['nullable', 'string', 'max:100'],
            'business_country' => ['nullable', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'min:8', 'max:72'],
        ]);

        try {
            abort_if(
                $user->is($request->user()) && (
                    $data['status'] !== 'active'
                    || $data['role'] !== 'ADMIN'
                    || strcasecmp((string) $user->email, (string) $data['email']) !== 0
                ),
                422,
                'You cannot deactivate, demote, or change the email of your own administrator account.'
            );

            if ((int) $data['tier'] === 4 && trim((string) ($data['business_name'] ?? '')) === '') {
                throw new \RuntimeException('Tier 4 Merchant requires a business/company name.');
            }

            $data['username'] = strtolower(trim($data['username']));
            $reserved = collect(config('semizzy.username_policy.reserved', []))->map(fn ($value) => strtolower((string) $value));
            $protected = collect(config('semizzy.username_policy.protected_terms', []))->map(fn ($value) => strtolower((string) $value));
            if ($reserved->contains($data['username']) || $protected->contains(fn ($term) => $term !== '' && str_contains($data['username'], $term))) {
                throw new \RuntimeException('That username is reserved or protected.');
            }
            $data['phone'] = $data['phone'] !== null
                ? preg_replace('/[^0-9+]/', '', $data['phone'])
                : null;

            $emailChanged = strcasecmp((string) $user->email, (string) $data['email']) !== 0;
            $phoneChanged = (string) ($user->phone ?? '') !== (string) ($data['phone'] ?? '');

            DB::transaction(function () use ($user, $data, $emailChanged, $phoneChanged): void {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
                $demotingAdmin = $lockedUser->role === 'ADMIN' && $data['role'] !== 'ADMIN';
                $deactivatingAdmin = $lockedUser->role === 'ADMIN' && $data['status'] !== 'active';

                if ($demotingAdmin || $deactivatingAdmin) {
                    $activeAdminIds = User::query()
                        ->where('role', 'ADMIN')
                        ->where('status', 'active')
                        ->lockForUpdate()
                        ->pluck('id');

                    abort_if(
                        $activeAdminIds->count() < 2 || ! $activeAdminIds->contains(fn ($id): bool => (int) $id !== (int) $lockedUser->id),
                        422,
                        'You cannot remove or deactivate the last active administrator.'
                    );
                }

                $payload = [
                    'name' => ((int) $data['tier'] === 4 && trim((string) ($data['business_name'] ?? '')) !== '') ? trim((string) $data['business_name']) : trim($data['name']),
                    'username' => $data['username'],
                    'email' => strtolower(trim($data['email'])),
                    'phone' => $data['phone'],
                    'role' => $data['role'],
                    'status' => $data['status'],
                    'tier' => (int) $data['tier'],
                    'account_type' => (int) $data['tier'] === 4 ? 'merchant' : 'personal',
                    'business_name' => trim((string) ($data['business_name'] ?? '')) ?: null,
                    'business_registration_number' => trim((string) ($data['business_registration_number'] ?? '')) ?: null,
                    'business_type' => trim((string) ($data['business_type'] ?? '')) ?: null,
                    'business_address' => trim((string) ($data['business_address'] ?? '')) ?: null,
                    'business_state' => trim((string) ($data['business_state'] ?? '')) ?: null,
                    'business_country' => trim((string) ($data['business_country'] ?? '')) ?: null,
                    'merchant_verified_at' => (int) $data['tier'] === 4 ? now() : null,
                    'tier_upgrade_status' => (int) $data['tier'] === 4 ? 'approved' : 'none',
                ];

                if ($emailChanged) {
                    $payload['email_verified_at'] = null;
                }
                if ($phoneChanged) {
                    $payload['phone_verified_at'] = null;
                }
                if (! empty($data['password'])) {
                    $payload['password'] = Hash::make($data['password']);
                }

                $lockedUser->forceFill($payload)->saveOrFail();
            });

            try {
                $audit->record('admin.user.updated', $user->fresh(), [
                    'target_user_id' => $user->id,
                    'role' => $data['role'],
                    'status' => $data['status'],
                    'tier' => (int) $data['tier'],
                    'email_changed' => $emailChanged,
                    'phone_changed' => $phoneChanged,
                    'password_reset' => ! empty($data['password']),
                ], $request);
            } catch (\Throwable $auditException) {
                report($auditException);
            }

            return back()->with('success', 'User account updated.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', $e->getMessage() ?: 'User account update failed safely.');
        }
    }

    public function verify(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'email_verified' => ['required', 'boolean'],
            'phone_verified' => ['required', 'boolean'],
        ]);

        try {
            if ($user->is($request->user()) && (! $data['email_verified'] || ! $data['phone_verified'])) {
                throw new \RuntimeException('You cannot remove verification from your own administrator account.');
            }

            if ($data['phone_verified'] && trim((string) $user->phone) === '') {
                throw new \RuntimeException('A phone number is required before phone verification can be enabled.');
            }

            $user->forceFill([
                'email_verified_at' => $data['email_verified'] ? now() : null,
                'phone_verified_at' => $data['phone_verified'] ? now() : null,
            ])->saveOrFail();

            try {
                $audit->record('admin.user.verification.updated', $user->fresh(), [
                    'target_user_id' => $user->id,
                    'email_verified' => (bool) $data['email_verified'],
                    'phone_verified' => (bool) $data['phone_verified'],
                ], $request);
            } catch (\Throwable $auditException) {
                report($auditException);
            }

            return back()->with('success', 'User verification status updated.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', $e->getMessage() ?: 'User verification update failed safely.');
        }
    }

    public function permissions(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['boolean'],
        ]);

        try {
            $catalog = collect(config('semizzy.role_permissions', []))->flatten()->unique()->values();
            DB::transaction(function () use ($user, $data, $catalog): void {
                UserPermissionOverride::query()->where('user_id', $user->id)->delete();

                foreach ($catalog as $permission) {
                    if (array_key_exists($permission, $data['permissions'] ?? [])) {
                        UserPermissionOverride::create([
                            'user_id' => $user->id,
                            'permission' => $permission,
                            'allowed' => (bool) $data['permissions'][$permission],
                        ]);
                    }
                }
            });

            try {
                $audit->record('admin.user.permissions.updated', $user->fresh(), [
                    'target_user_id' => $user->id,
                    'permissions' => $data['permissions'] ?? [],
                ], $request);
            } catch (\Throwable $auditException) { report($auditException); }

            return back()->with('success', 'User permissions updated.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', $e->getMessage() ?: 'User permissions update failed safely.');
        }
    }

    public function walletStatus(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:active,frozen']]);

        try {
            DB::transaction(function () use ($user, $data): void {
                $wallet = WalletAccount::query()->where('user_id', $user->id)->lockForUpdate()->first();
                if (! $wallet) {
                    throw new \RuntimeException('This user does not have a wallet yet.');
                }
                $wallet->forceFill(['status' => $data['status']])->saveOrFail();
            });

            try {
                $audit->record('admin.user.wallet.status.updated', $user->fresh(), [
                    'target_user_id' => $user->id, 'status' => $data['status'],
                ], $request);
            } catch (\Throwable $auditException) { report($auditException); }

            return back()->with('success', 'User wallet status updated.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', $e->getMessage() ?: 'Wallet status update failed safely.');
        }
    }

    public function fund(
        Request $request,
        User $user,
        AdminWalletFundingService $funding,
        AuditLogger $audit
    ): RedirectResponse {
        $data = $request->validate([
            'amount' => ['required', 'string', 'max:30', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $wallet = $funding->fund($user, $data['amount'], $request->user(), trim((string) ($data['note'] ?? '')));

            try {
                $audit->record('admin.user.wallet.funded', $user->fresh(), [
                    'target_user_id' => $user->id,
                    'amount_major' => $data['amount'],
                    'currency' => $wallet->currency,
                    'wallet_account_id' => $wallet->id,
                    'note' => trim((string) ($data['note'] ?? '')) ?: null,
                ], $request);
            } catch (\Throwable $auditException) {
                report($auditException);
            }

            return back()->with('success', 'User wallet funded successfully.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', $e->getMessage() ?: 'User funding failed safely.');
        }
    }
}
