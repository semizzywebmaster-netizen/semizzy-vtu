<?php
namespace App\Services\Communication;
use App\Models\CommunicationCampaign;
use App\Models\User;
use App\Notifications\CoreNotification;
use Illuminate\Support\Facades\Notification;
class CommunicationCenterService {
 public function recipients(array $targets){
  $q=User::query()->where('status','active');
  foreach($targets as $key=>$value){
   if($key==='all' && $value)$q=User::query();
   elseif($key==='tiers' && is_array($value))$q->whereIn('tier',$value);
   elseif($key==='roles' && is_array($value))$q->whereIn('role',$value);
   elseif($key==='verified' && $value===true)$q->whereNotNull('email_verified_at');
   elseif($key==='verified' && $value===false)$q->whereNull('email_verified_at');
   elseif($key==='new_days')$q->where('created_at','>=',now()->subDays((int)$value));
   elseif($key==='inactive_days')$q->where('last_login_at','<',now()->subDays((int)$value));
   elseif($key==='user_ids' && is_array($value))$q->whereIn('id',$value);
  }
  return $q;
 }
 public function send(CommunicationCampaign $campaign):int {
  $count=0;
  $channels=$campaign->channels??[];
  if(in_array('web_push',$channels,true)){
   $campaign->creator; // channel adapter placeholder: database notification remains authoritative until push provider is configured.
  }
  $users=$this->recipients($campaign->targets??[])->get(['id']);
  foreach($users as $user){ if(in_array('web_push',$channels,true)||in_array('email',$channels,true)||in_array('sms',$channels,true)||in_array('whatsapp',$channels,true)){ $user->notify(new CoreNotification($campaign->title,$campaign->message,$campaign->url)); $count++; } }
  $campaign->forceFill(['status'=>'sent','sent_at'=>now()])->save();
  return $count;
 }
}