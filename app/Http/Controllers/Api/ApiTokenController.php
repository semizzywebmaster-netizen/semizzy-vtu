<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Security\SecurityEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ApiTokenController extends Controller
{
    public function store(Request $request, SecurityEventLogger $security): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['sometimes', 'array', 'min:1', 'max:50'],
            'abilities.*' => ['string', 'max:100', 'distinct', 'in:core.read,vtu.read,vtu.transact,vtu.bulk'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $maxLifetimeDays = max(1, (int) config('sanctum.token_max_lifetime_days', 365));
        $now = now();
        $maximumExpiry = $now->copy()->addDays($maxLifetimeDays);
        $expiresAt = isset($data['expires_at'])
            ? $now->copy()->parse($data['expires_at'])
            : $maximumExpiry->copy();

        if ($expiresAt->gt($maximumExpiry)) {
            throw ValidationException::withMessages([
                'expires_at' => ["Token expiry cannot be more than {$maxLifetimeDays} days from now."],
            ]);
        }

        $abilities = $data['abilities'] ?? ['core.read'];

        $token = $request->user()->createToken(
            $data['name'],
            $abilities,
            $expiresAt
        );

        $security->record('api_token.created', 'info', [
            'token_id' => $token->accessToken->getKey(),
            'name' => $data['name'],
            'abilities' => $abilities,
        ], $request);

        return response()->json([
            'message' => 'Token created. Store the token securely; it will not be shown again.',
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->tokens()
                ->latest('id')
                ->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at']),
        ]);
    }

    public function destroy(Request $request, int $token): JsonResponse
    {
        $deleted = $request->user()->tokens()->whereKey($token)->delete();

        if ($deleted === 0) {
            return response()->json(['message' => 'Token not found.'], 404);
        }

        app(SecurityEventLogger::class)->record('api_token.revoked', 'info', ['token_id' => $token], $request);

        return response()->json(['message' => 'Token revoked.']);
    }

    public function revokeAll(Request $request): JsonResponse
    {
        $count = $request->user()->tokens()->delete();

        app(SecurityEventLogger::class)->record('api_token.revoked_all', 'info', ['count' => $count], $request);

        return response()->json(['message' => 'All API tokens revoked.', 'count' => $count]);
    }
}
