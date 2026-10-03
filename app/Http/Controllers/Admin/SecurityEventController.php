<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
class SecurityEventController extends Controller {
 public function index(Request $request): Response {
  $v=$request->validate(['severity'=>['nullable','in:info,warning,critical'],'event'=>['nullable','string','max:120'],'user_id'=>['nullable','integer','exists:users,id'],'request_id'=>['nullable','string','max:120'],'from'=>['nullable','date'],'to'=>['nullable','date']]);
  $q=SecurityEvent::query()->with('user:id,name,email')->latest();
  if(!empty($v['severity'])) $q->where('severity',$v['severity']);
  if(!empty($v['event'])) $q->where('event','like','%'.$v['event'].'%');
  if(!empty($v['user_id'])) $q->where('user_id',$v['user_id']);
  if(!empty($v['request_id'])) $q->where('request_id',$v['request_id']);
  if(!empty($v['from'])) $q->where('created_at','>=',Carbon::parse($v['from'])->startOfDay());
  if(!empty($v['to'])) $q->where('created_at','<=',Carbon::parse($v['to'])->endOfDay());
  $events=$q->paginate(50)->withQueryString()->through(fn(SecurityEvent $e)=>['id'=>$e->id,'event'=>$e->event,'severity'=>$e->severity,'request_id'=>$e->request_id,'ip_address'=>$e->ip_address,'user_agent'=>$e->user_agent,'context'=>$this->sanitize(is_array($e->context) ? $e->context : []),'user'=>$e->user?['id'=>$e->user->id,'name'=>$e->user->name,'email'=>$e->user->email]:null,'created_at'=>$e->created_at?->toIso8601String()]);
  return Inertia::render('Admin/SecurityEvents',['events'=>$events,'filters'=>$v]);
 }

 private function sanitize(array $context): array {
  $sensitiveKeys=['token','access_token','api_key','secret','password','authorization','credentials','client_secret','private_key','refresh_token','otp','one_time_code','webhook_secret'];
  $safe=[];
  foreach($context as $key=>$value) {
   $normalized=strtolower((string) $key);
   $sensitive=false;
   foreach($sensitiveKeys as $needle) {
    if(str_contains($normalized,$needle)) { $sensitive=true; break; }
   }
   $safe[$key]=$sensitive ? '[REDACTED]' : (is_array($value) ? $this->sanitize($value) : $value);
  }
  return $safe;
 }
}