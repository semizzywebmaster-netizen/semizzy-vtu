<?php
namespace Semizzy\Addons\MailerSmtp\Models;
use Illuminate\Database\Eloquent\Model;
class MailerSmtpAttempt extends Model {protected $table='mailer_smtp_attempts';protected $guarded=[];protected function casts():array{return ['attempted_at'=>'datetime'];}}