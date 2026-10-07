<?php
namespace Semizzy\Addons\MailerSmtp\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Semizzy\Addons\MailerSmtp\Models\MailerSmtpProfile;
use Semizzy\Addons\MailerSmtp\Models\MailerSmtpAttempt;
class MailerSmtpService {
 public function profiles(){return MailerSmtpProfile::query()->orderBy('priority')->orderBy('id')->get();}
 public function configure():bool {
  if(!Schema::hasTable('mailer_smtp_profiles'))return false;
  $profiles=MailerSmtpProfile::query()->where('enabled',true)->where(function($q){$q->whereNull('cooldown_until')->orWhere('cooldown_until','<=',now());})->whereNotNull('username')->whereNotNull('password')->where('host','!=','')->orderBy('priority')->orderByDesc('weight')->get();
  if($profiles->isEmpty())return false;
  $mailers=[];$names=[];
  foreach($profiles as $p){$name='addon_smtp_'.$p->id;$mailers[$name]=['transport'=>'smtp','host'=>$p->host,'port'=>$p->port,'encryption'=>$p->encryption==='null'?null:$p->encryption,'username'=>$p->username,'password'=>$p->password,'timeout'=>15,'auth_mode'=>null];$names[]=$name;}
  $stored=SystemSetting::query()->where('key','mailer_smtp')->value('value');$settings=is_string($stored)?json_decode($stored,true):[];$strategy=in_array((string)($settings['strategy']??config('mailer_smtp.strategy','failover')),['failover','roundrobin'],true)?($settings['strategy']??'failover'):'failover';
  config(['mail.mailers'=>array_merge(config('mail.mailers',[]),$mailers,['mailer_smtp_pool'=>['transport'=>$strategy,'mailers'=>$names,'retry_after'=>60]]),'mail.default'=>'mailer_smtp_pool']);
  $first=$profiles->first();config(['mail.from.address'=>$first->from_address,'mail.from.name'=>$first->from_name ?: config('mail.from.name')]);
  return true;
 }
 public function test(MailerSmtpProfile $profile,string $recipient):void {
  $operation='smtp-test:'.$profile->id.':'.Str::uuid();
  $name='mailer_smtp_test_'.$profile->id;
  config(['mail.mailers.'.$name=>['transport'=>'smtp','host'=>$profile->host,'port'=>$profile->port,'encryption'=>$profile->encryption==='null'?null:$profile->encryption,'username'=>$profile->username,'password'=>$profile->password,'timeout'=>15]]);
  try{MailerSmtpAttempt::create(['profile_id'=>$profile->id,'operation_key'=>$operation,'status'=>'started','attempted_at'=>now()]);Mail::mailer($name)->raw('SEMIZZY ONE SMTP test email',function($m)use($profile,$recipient){$m->to($recipient)->subject('SEMIZZY ONE SMTP test — '.$profile->name)->from($profile->from_address,$profile->from_name);});$profile->forceFill(['health_status'=>'healthy','last_success_at'=>now(),'last_health_check_at'=>now(),'failure_count'=>0,'cooldown_until'=>null,'last_error'=>null])->save();MailerSmtpAttempt::where('operation_key',$operation)->update(['status'=>'success']);}
  catch(\Throwable $e){$profile->forceFill(['health_status'=>'failed','last_failure_at'=>now(),'last_health_check_at'=>now(),'failure_count'=>DB::raw('failure_count + 1'),'cooldown_until'=>now()->addSeconds((int)config('mailer_smtp.cooldown_seconds',300)),'last_error'=>substr($e->getMessage(),0,500)])->save();MailerSmtpAttempt::where('operation_key',$operation)->update(['status'=>'failed','error'=>substr($e->getMessage(),0,500)]);throw $e;}
 }
}