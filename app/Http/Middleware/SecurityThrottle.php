<?php
namespace App\Http\Middleware;
use App\Services\Security\SecurityEventLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
class SecurityThrottle {
 public function __construct(private readonly SecurityEventLogger $securityEvents) {}
 public function handle(Request $request, Closure $next, string $scope, int $maxAttempts=10, int $decaySeconds=60): Response {
  $identity=$request->user()?->getAuthIdentifier() ?? $request->ip() ?? 'unknown';
  $key='security:'.$scope.':'.sha1((string)$identity);
  if (RateLimiter::tooManyAttempts($key,$maxAttempts)) {
   $this->securityEvents->record('security.rate_limited','warning',['scope'=>$scope,'max_attempts'=>$maxAttempts,'retry_after'=>RateLimiter::availableIn($key)],$request);
   return response()->json(['message'=>'Too many requests. Please try again later.','retry_after'=>RateLimiter::availableIn($key)],429);
  }
  RateLimiter::hit($key,$decaySeconds);
  return $next($request);
 }
}