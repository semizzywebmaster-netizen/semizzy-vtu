<?php

namespace App\Http\Middleware;

use App\Services\Platform\FeatureRolloutService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureRollout
{
    public function __construct(private readonly FeatureRolloutService $rollouts)
    {
    }

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless($this->rollouts->allows($feature, $request->user()), 404);
        return $next($request);
    }
}
