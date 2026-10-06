<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class EnsureApiUser {
 public function handle(Request $request, Closure $next) {
  abort_unless($request->user() && (int)$request->user()->tier===5,403,'API access is available only to API User accounts.');
  return $next($request);
 }
}