<?php

namespace App\Http\Middleware;

use App\Models\Addon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVtuAddonActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $active = Addon::query()
            ->where('identifier', 'vtu.digital-services')
            ->where('status', 'active')
            ->exists();

        abort_unless($active, 404, 'VTU & Digital Services addon is not active.');

        return $next($request);
    }
}
