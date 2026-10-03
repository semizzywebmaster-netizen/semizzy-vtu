<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        ]);

        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'status', 'email_verified_at', 'created_at'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'emailVerified' => $user->email_verified_at !== null,
                'createdAt' => $user->created_at?->toISOString(),
                'isSelf' => $user->id === $request->user()->id,
            ]);

        return Inertia::render('Admin/Users', ['users' => $users, 'filters' => $filters]);
    }

    public function update(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'in:ADMIN,STAFF,SUPPORT,USER'],
            'status' => ['required', 'in:active,suspended,disabled'],
        ]);

        abort_if(
            $user->is($request->user()) && ($data['status'] !== 'active' || $data['role'] !== $request->user()->role),
            422,
            'You cannot deactivate or change your own role.'
        );

        DB::transaction(function () use ($user, $data): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $demotingAdmin = $lockedUser->role === 'ADMIN' && $data['role'] !== 'ADMIN';
            $deactivatingAdmin = $lockedUser->role === 'ADMIN' && $data['status'] !== 'active';

            if ($demotingAdmin || $deactivatingAdmin) {
                // Lock the complete active-admin set before checking the invariant.
                // This serializes competing demotions/deactivations and prevents two
                // concurrent requests from both removing the final two administrators.
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

            $lockedUser->update($data);
        });

        $audit->record('admin.user.updated', $user->fresh(), [
            'target_user_id' => $user->id,
            'role' => $data['role'],
            'status' => $data['status'],
        ], $request);

        return back()->with('success', 'User account updated.');
    }
}
