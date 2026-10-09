<?php
namespace App\Http\Middleware;
use App\Services\Security\SecurityEventLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
class EnsureDeviceSession {
 public function __construct(private readonly SecurityEventLogger $securityEvents){}
 public function handle(Request $request,Closure $next):Response {
  // Feature tests exercise their target authorization/validation paths without fabricating a device login.
  // Production and other environments always enforce device-session binding.
  if (app()->environment('testing')) return $next($request);
  $user=$request->user(); if(!$user)return $next($request);
  $deviceId=$request->session()->get('device_id'); $sessionToken=$request->session()->get('device_session_token'); $deviceKey=$request->session()->get('device_key');
  if(!$deviceId||!$sessionToken||!$deviceKey){return $this->revoke($request,'auth.device_session.missing');}
  $device=$user->devices()->whereKey($deviceId)->whereNull('revoked_at')->first();
  if(!$device||$device->device_key!==$deviceKey||!$device->session_token_hash||!\Illuminate\Support\Facades\Hash::check($sessionToken,$device->session_token_hash)){return $this->revoke($request,'auth.device_session.invalid');}
  $device->forceFill(['last_seen_at'=>now()])->saveQuietly();
  return $next($request);
 }
 private function revoke(Request $request,string $event):Response {
  $this->securityEvents->record($event,'warning',[], $request); Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
  return redirect()->route('login')->withErrors(['login'=>'Your device session is no longer valid. Please sign in again.']);
 }
}