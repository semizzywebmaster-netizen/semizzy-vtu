<?php
namespace Semizzy\Addons\Rewards\Services;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Semizzy\Addons\Rewards\Models\RewardReferralCode;
use Semizzy\Addons\Rewards\Models\RewardReferral;
use Semizzy\Addons\Rewards\Models\RewardRule;
use Semizzy\Addons\Rewards\Models\RewardEvent;
use Semizzy\Addons\Rewards\Models\RewardCoupon;
use Semizzy\Addons\Rewards\Models\RewardCouponRedemption;
class RewardsService {
 public function createReferralCode(int $userId, ?string $requested=null): RewardReferralCode { return DB::transaction(function()use($userId,$requested){ $base=strtoupper(preg_replace('/[^A-Z0-9]/','',($requested?:'REF'.Str::random(8)))); $code=$base; for($i=0;$i<5 && RewardReferralCode::where('code',$code)->exists();$i++)$code=$base.Str::random(3); if(RewardReferralCode::where('code',$code)->exists())throw new RuntimeException('Unable to create a unique referral code.'); return RewardReferralCode::create(['user_id'=>$userId,'code'=>$code,'active'=>true]); }); }
 public function attributeReferral(int $referredId,string $code): RewardReferral { return DB::transaction(function()use($referredId,$code){ $c=RewardReferralCode::query()->whereRaw('UPPER(code)=?',[strtoupper(trim($code))])->lockForUpdate()->first(); if(!$c||!$c->active)throw new RuntimeException('Referral code is invalid or inactive.'); if((int)$c->user_id===$referredId)throw new RuntimeException('A user cannot refer themselves.'); if($c->max_uses!==null&&$c->uses_count>=$c->max_uses)throw new RuntimeException('Referral code usage limit reached.'); if(RewardReferral::where('referred_id',$referredId)->exists())throw new RuntimeException('User already has a referral attribution.'); $r=RewardReferral::create(['referrer_id'=>$c->user_id,'referred_id'=>$referredId,'referral_code_id'=>$c->id,'status'=>'pending','source'=>'code']); $c->increment('uses_count'); return $r; }); }
 public function qualifyReferral(int $referralId,string $qualification,array $metadata=[]): RewardReferral { return DB::transaction(function()use($referralId,$qualification,$metadata){ $r=RewardReferral::query()->lockForUpdate()->findOrFail($referralId); if($r->status==='rewarded')return $r->fresh(); $r->status='qualified';$r->qualified_at=$r->qualified_at?:now();$r->metadata=array_merge((array)$r->metadata,['qualification'=>$qualification],$metadata);$r->save();return $r->fresh(); }); }
 public function issueReward(int $userId,string $eventKey,string $operationKey,?RewardRule $rule=null,array $metadata=[]): RewardEvent { return DB::transaction(function()use($userId,$eventKey,$operationKey,$rule,$metadata){ $existing=RewardEvent::where('operation_key',$operationKey)->lockForUpdate()->first();if($existing)return $existing;$rule=$rule?:RewardRule::where('event_key',$eventKey)->where('active',true)->latest()->first();$amount=$rule?(string)$rule->amount:'0.00';$currency=$rule?strtoupper($rule->currency):'NGN';return RewardEvent::create(['user_id'=>$userId,'rule_id'=>$rule?->id,'event_key'=>$eventKey,'operation_key'=>$operationKey,'status'=>'pending','amount'=>$amount,'currency'=>$currency,'metadata'=>$metadata]); }); }
 public function approveReward(int $rewardEventId, int $adminId, ?string $note=null): RewardEvent
 {
  return DB::transaction(function() use ($rewardEventId,$adminId,$note) {
   $event=RewardEvent::query()->lockForUpdate()->findOrFail($rewardEventId);
   if ($event->approval_status === 'approved') return $event->fresh();
   if ($event->approval_status === 'rejected') throw new RuntimeException('This reward has already been rejected.');
   if (!$event->user_id) throw new RuntimeException('A reward must have a user before approval.');
   $rule=$event->rule_id ? RewardRule::query()->find($event->rule_id) : null;
   if ($rule) {
    if ($rule->max_global_redemptions !== null && RewardEvent::where('rule_id',$rule->id)->where('approval_status','approved')->count() >= $rule->max_global_redemptions) throw new RuntimeException('Reward rule global approval limit reached.');
    if ($rule->max_user_redemptions !== null && RewardEvent::where('rule_id',$rule->id)->where('user_id',$event->user_id)->where('approval_status','approved')->count() >= $rule->max_user_redemptions) throw new RuntimeException('Reward rule per-user approval limit reached.');
   }
   $amountMinor=(string) round(((float)$event->amount)*100);
   $wallet=\App\Models\WalletAccount::query()->where('user_id',$event->user_id)->where('currency',$event->currency)->lockForUpdate()->first();
   if(!$wallet) throw new RuntimeException('User wallet is not available.');
   $before=(string)$wallet->available_minor;
   $after=(string)((int)$before+(int)$amountMinor);
   $wallet->forceFill(['available_minor'=>$after])->saveOrFail();
   $reference='RWD-'.strtoupper(Str::random(12));
   \App\Models\WalletMovement::create(['wallet_account_id'=>$wallet->id,'operation_key'=>'reward:'.$event->operation_key,'reference'=>$reference,'type'=>'credit','amount_minor'=>$amountMinor,'currency'=>$event->currency,'available_before_minor'=>$before,'available_after_minor'=>$after,'held_before_minor'=>(string)$wallet->held_minor,'held_after_minor'=>(string)$wallet->held_minor,'metadata'=>['reward_event_id'=>$event->id,'approved_by'=>$adminId]]);
   $event->forceFill(['status'=>'completed','approval_status'=>'approved','approved_by'=>$adminId,'approved_at'=>now(),'approval_note'=>$note,'wallet_reference'=>$reference])->saveOrFail();
   return $event->fresh();
  });
 }
 public function rejectReward(int $rewardEventId, int $adminId, ?string $note=null): RewardEvent
 {
  return DB::transaction(function() use ($rewardEventId,$adminId,$note) {
   $event=RewardEvent::query()->lockForUpdate()->findOrFail($rewardEventId);
   if($event->approval_status==='approved') throw new RuntimeException('An approved reward cannot be rejected.');
   $event->forceFill(['status'=>'rejected','approval_status'=>'rejected','approved_by'=>$adminId,'approved_at'=>now(),'approval_note'=>$note])->saveOrFail();
   return $event->fresh();
  });
 }
 public function redeemCoupon(int $userId,string $code,string $operationKey,float $orderAmount,string $currency='NGN'): RewardCouponRedemption { return DB::transaction(function()use($userId,$code,$operationKey,$orderAmount,$currency){ $coupon=RewardCoupon::query()->whereRaw('UPPER(code)=?',[strtoupper(trim($code))])->lockForUpdate()->first();if(!$coupon||!$coupon->active)throw new RuntimeException('Coupon is invalid or inactive.');$now=now();if($coupon->starts_at&&$now->lt($coupon->starts_at))throw new RuntimeException('Coupon is not active yet.');if($coupon->ends_at&&$now->gt($coupon->ends_at))throw new RuntimeException('Coupon has expired.');if(strtoupper($coupon->currency)!==strtoupper($currency))throw new RuntimeException('Coupon currency mismatch.');if($coupon->max_redemptions!==null&&$coupon->redemptions_count>=$coupon->max_redemptions)throw new RuntimeException('Coupon redemption limit reached.');$existing=RewardCouponRedemption::where('operation_key',$operationKey)->first();if($existing)return $existing;if(RewardCouponRedemption::where('user_id',$userId)->where('coupon_id',$coupon->id)->count()>=($coupon->per_user_limit??PHP_INT_MAX))throw new RuntimeException('Per-user coupon limit reached.');$value=$coupon->discount_type==='percent'?min($orderAmount,round($orderAmount*((float)$coupon->discount_value/100),2)):min($orderAmount,(float)$coupon->discount_value);$red=RewardCouponRedemption::create(['coupon_id'=>$coupon->id,'user_id'=>$userId,'operation_key'=>$operationKey,'discount_value'=>number_format($value,2,'.',''),'currency'=>strtoupper($currency)]);$coupon->increment('redemptions_count');return $red; }); }
}