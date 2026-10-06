<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = trim((string) $request->header('X-Request-ID'));

        if ($requestId === '' || strlen($requestId) > 128 || ! preg_match('/^[A-Za-z0-9._:-]+$/', $requestId)) {
            $requestId = (string) Str::uuid();
        }

        $request->attributes->set('request_id', $requestId);

        $response = $next($request);

        $response->headers->set('X-Request-ID', $requestId);
        return $response;
    }
}
