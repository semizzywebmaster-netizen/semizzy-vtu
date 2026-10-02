<?php

namespace App\Http\Middleware;

use App\Services\Security\SecurityEventLogger;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveApiAccount
{
    public function __construct(private readonly SecurityEventLogger $securityEvents)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status !== 'active') {
            $this->securityEvents->record('auth.inactive_account.api_denied', 'warning', [
                'account_status' => $user->status,
            ], $request);

            return new JsonResponse(['message' => 'Your account is not active. Contact support if you need assistance.'], 403);
        }

        return $next($request);
    }
}
