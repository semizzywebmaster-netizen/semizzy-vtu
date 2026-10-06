<?php
namespace App\Http\Controllers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
class ApiAccessController extends Controller {
 public function index(Request $request): Response {
  abort_unless((int)$request->user()->tier===5,403);
  return Inertia::render('ApiAccess',['tokens'=>$request->user()->tokens()->latest('id')->get(['id','name','abilities','last_used_at','expires_at','created_at']),'newToken'=>session('api_plain_token')]);
 }
 public function store(Request $request): RedirectResponse {
  abort_unless((int)$request->user()->tier===5,403);
  $data=$request->validate(['name'=>['required','string','max:100']]);
  $abilities=['core.read','vtu.read','vtu.transact','vtu.bulk'];
  $max=max(1,(int)config('sanctum.token_max_lifetime_days',365));
  $expires=now()->addDays($max);
  $token=$request->user()->createToken($data['name'],$abilities,$expires);
  return redirect()->route('api.access')->with('api_plain_token',$token->plainTextToken)->with('success','API key created. Copy it now; it will not be shown again.');
 }
 public function destroy(Request $request,int $token): RedirectResponse {
  abort_unless((int)$request->user()->tier===5,403);
  $request->user()->tokens()->whereKey($token)->delete();
  return back()->with('success','API key revoked.');
 }
}