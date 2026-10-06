<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;
class EnsureTransactionPin {
 public function handle(Request $request, Closure $next): Response {
  $user=$request->user();
  if(!$user) return $next($request);
  if(!$user->transaction_pin_hash) return $request->expectsJson()
   ? response()->json(['message'=>'A 4-digit transaction PIN is required before this transaction can be made.','code'=>'transaction_pin_required'],422)
   : back()->withErrors(['transaction_pin'=>'Set your 4-digit transaction PIN before making transactions.']);
  $pin=(string)$request->input('transaction_pin','');
  if(!preg_match('/^\d{4}$/',$pin) || !Hash::check($pin,(string)$user->transaction_pin_hash)){
   return $request->expectsJson()
    ? response()->json(['message'=>'Invalid transaction PIN.','code'=>'transaction_pin_invalid'],422)
    : back()->withErrors(['transaction_pin'=>'Invalid transaction PIN.']);
  }
  return $next($request);
 }
}