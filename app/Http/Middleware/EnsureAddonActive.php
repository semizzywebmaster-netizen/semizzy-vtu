<?php

namespace App\Http\Middleware;

use App\Models\Addon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAddonActive
{
    public function handle(Request $request, Closure $next, string $identifier): Response
    {
        $addon = Addon::query()->where('identifier', strtolower(trim($identifier)))->first();

        if (!$addon || $addon->status !== 'active') {
            abort(404);
        }

        return $next($request);
    }
}
