<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
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
            'tier' => ['nullable', 'integer', 'in:1,2,3'],
        ]);

        $users = User::query()
            ->select([
                'id', 'name', 'username', 'email', 'phone', 'role', 'status', 'tier',
                'email_verified_at', 'phone_verified_at', 'created_at',
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
                'tier' => max(1, min(3, (int) $user->tier)),
                'emailVerified' => $user->email_verified_at !== null,
                'phoneVerified' => $user->phone_verified_at !== null,
                'createdAt' => $user->created_at?->toISOString(),
                'isSelf' => $user->id === $request->user()->id,
            ]);

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'filters' => $filters,
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
            'tier' => ['required', 'integer', 'in:1,2,3'],
            'password' => ['nullable', 'string', 'min:8', 'max:72'],
        ]);

        try {
            abort_if(
                $user->is($request->user()) && ($data['status'] !== 'active' || $data['role'] !== 'ADMIN'),
                422,
                'You cannot deactivate or change your own administrator role.'
            );

            $data['username'] = strtolower(trim($data['username']));
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
                    'name' => trim($data['name']),
                    'username' => $data['username'],
                    'email' => strtolower(trim($data['email'])),
                    'phone' => $data['phone'],
                    'role' => $data['role'],
                    'status' => $data['status'],
                    'tier' => (int) $data['tier'],
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
