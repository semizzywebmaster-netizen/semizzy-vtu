<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Semizzy\Addons\MailerSmtp\Models\MailerSmtpProfile;
use Semizzy\Addons\MailerSmtp\Services\MailerSmtpService;
class MailerSmtpController extends Controller {
 public function index(){return Inertia::render('Admin/MailerSmtp',['profiles'=>MailerSmtpProfile::query()->orderBy('priority')->orderBy('id')->get()->makeVisible('password')->map(fn($p)=>['id'=>$p->id,'name'=>$p->name,'provider'=>$p->provider,'host'=>$p->host,'port'=>$p->port,'encryption'=>$p->encryption,'username'=>$p->username,'from_address'=>$p->from_address,'from_name'=>$p->from_name,'enabled'=>$p->enabled,'priority'=>$p->priority,'weight'=>$p->weight,'health_status'=>$p->health_status,'failure_count'=>$p->failure_count,'last_success_at'=>$p->last_success_at,'last_failure_at'=>$p->last_failure_at]),'settings'=>json_decode((string) (SystemSetting::query()->where('key','mailer_smtp')->value('value') ?? '{}'), true) ?: ['strategy'=>'failover','cooldown_seconds'=>300]]);}
 public function store(Request $r){$d=$r->validate(['name'=>'required|string|max:120','provider'=>'required|string|max:40','host'=>'required|string|max:190','port'=>'required|integer|min:1|max:65535','encryption'=>'nullable|in:tls,ssl,null','username'=>'required|string|max:190','password'=>'required|string|max:500','from_address'=>'required|email|max:190','from_name'=>'nullable|string|max:190','enabled'=>'boolean','priority'=>'required|integer|min:1','weight'=>'required|integer|min:1']);$d['profile_key']=strtolower(str_replace(' ','-',trim($d['name']))).'-'.substr(sha1(uniqid('',true)),0,8);MailerSmtpProfile::create($d);return back()->with('success','SMTP profile added.');}
 public function update(Request $r,MailerSmtpProfile $profile){$d=$r->validate(['name'=>'sometimes|string|max:120','provider'=>'sometimes|string|max:40','host'=>'sometimes|string|max:190','port'=>'sometimes|integer|min:1|max:65535','encryption'=>'nullable|in:tls,ssl,null','username'=>'sometimes|string|max:190','password'=>'nullable|string|max:500','from_address'=>'sometimes|email|max:190','from_name'=>'nullable|string|max:190','enabled'=>'boolean','priority'=>'sometimes|integer|min:1','weight'=>'sometimes|integer|min:1']);if(array_key_exists('password',$d)&&($d['password']??'')==='')unset($d['password']);$profile->update($d);return back()->with('success','SMTP profile updated.');}
 public function toggle(MailerSmtpProfile $profile){$profile->update(['enabled'=>!$profile->enabled]);return back();}
 public function test(Request $r,MailerSmtpProfile $profile,MailerSmtpService $service){$d=$r->validate(['recipient'=>'required|email']);try{$service->test($profile,$d['recipient']);return back()->with('success','SMTP test succeeded.');}catch(\Throwable $e){return back()->with('error','SMTP test failed safely.');}}
 public function destroy(MailerSmtpProfile $profile){$profile->delete();return back()->with('success','SMTP profile removed.');}
 public function settings(Request $r){$d=$r->validate(['strategy'=>'required|in:failover,roundrobin','cooldown_seconds'=>'required|integer|min:30|max:86400']);SystemSetting::updateOrCreate(['key'=>'mailer_smtp'],['value'=>json_encode($d),'type'=>'json','is_secret'=>false]);config(['mailer_smtp.strategy'=>$d['strategy'],'mailer_smtp.cooldown_seconds'=>$d['cooldown_seconds']]);Cache::forget('mailer_smtp.settings');return back()->with('success','Mailer settings updated.');}
}