<?php

namespace App\Http\Middleware;

use App\Services\System\FeatureControlService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (app(FeatureControlService::class)->enabled($feature)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message'=>'This feature is temporarily unavailable.'], 503);
        }

        return response()->view('errors.feature-disabled', ['feature'=>$feature], 503);
    }
}
