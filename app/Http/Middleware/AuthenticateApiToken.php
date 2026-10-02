<?php

namespace App\Http\Middleware;

use App\Services\Security\SecurityEventLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user('sanctum');

        if (! $user) {
            app(SecurityEventLogger::class)->record('api_token.authentication_failed', 'warning');
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $token = $user->currentAccessToken();

        foreach ($abilities as $ability) {
            if (! $token || ! $token->can($ability)) {
                app(SecurityEventLogger::class)->record(
                    'api_token.ability_denied',
                    'warning',
                    ['ability' => $ability],
                    $request
                );
                return response()->json(['message' => 'Insufficient token ability.'], 403);
            }
        }

        return $next($request);
    }
}
