<?php
namespace Semizzy\Addons\MailerSmtp\Models;
use Illuminate\Database\Eloquent\Model;
class MailerSmtpProfile extends Model {
 protected $table='mailer_smtp_profiles'; protected $guarded=[];
 protected $hidden=['password'];
 protected function casts():array{return ['password'=>'encrypted','enabled'=>'boolean','weight'=>'integer','priority'=>'integer','failure_count'=>'integer','cooldown_until'=>'datetime','last_success_at'=>'datetime','last_failure_at'=>'datetime','last_health_check_at'=>'datetime'];}
}