<?php
namespace Addons\BusinessAgentMerchantReseller\Services;
use Addons\BusinessAgentMerchantReseller\Models\{BusinessProfile,BusinessPartner,BusinessPartnerEvent};
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;
class BusinessPartnerService{
 public function apply(User $user,array $data,string $type):BusinessPartner{
  if(!in_array($type,['agent','merchant','reseller'],true))throw new RuntimeException('Invalid partner type.');
  $existing=BusinessPartner::where('user_id',$user->id)->where('type',$type)->whereIn('status',['pending','approved','active'])->first();
  if($existing)return $existing;
  $business=BusinessProfile::firstOrCreate(['user_id'=>$user->id],['business_name'=>$data['business_name'],'business_type'=>$data['business_type']??'business','registration_number'=>$data['registration_number']??null,'contact_phone'=>$data['contact_phone']??null,'contact_email'=>$data['contact_email']??null,'status'=>'pending']);
  $p=BusinessPartner::create(['user_id'=>$user->id,'business_profile_id'=>$business->id,'type'=>$type,'code'=>strtoupper($type[0]).'-'.strtoupper(Str::random(10)),'status'=>'pending','metadata'=>$data]);
  BusinessPartnerEvent::create(['business_partner_id'=>$p->id,'actor_user_id'=>$user->id,'action'=>'applied','status'=>'pending']);
  return $p;
 }
 public function approve(BusinessPartner $p,User $actor,bool $approve,string $note=''):BusinessPartner{
  $p->update(['status'=>$approve?'active':'rejected']);
  $p->events()->create(['actor_user_id'=>$actor->id,'action'=>$approve?'approved':'rejected','status'=>$p->status,'note'=>$note]);
  if($approve&&$p->business)$p->business->update(['status'=>'approved']);
  return $p->fresh();
 }
}
