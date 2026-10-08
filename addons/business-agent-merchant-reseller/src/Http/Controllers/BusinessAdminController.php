<?php
namespace Addons\BusinessAgentMerchantReseller\Http\Controllers;

use Addons\BusinessAgentMerchantReseller\Models\{BusinessPartner,BusinessCommissionSettlement};
use Addons\BusinessAgentMerchantReseller\Services\BusinessPartnerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BusinessAdminController
{
 public function page(){return Inertia::render('BusinessAdmin');}

 public function partners(){
  return response()->json(['partners'=>BusinessPartner::with(['user','business'])->latest()->paginate(100)]);
 }

 public function approve(Request $r,BusinessPartner $partner,BusinessPartnerService $s){
  $d=$r->validate(['approved'=>'required|boolean','note'=>'nullable|string|max:1000']);
  return response()->json(['partner'=>$s->approve($partner,$r->user(),$d['approved'],$d['note']??'')]);
 }

 public function bulkStatus(Request $r){
  $d=$r->validate([
   'partner_ids'=>['required','array','min:1','max:100'],
   'partner_ids.*'=>['integer','distinct','exists:business_partners,id'],
   'status'=>['required','in:active,rejected,suspended'],
   'note'=>['nullable','string','max:1000'],
  ]);
  if(in_array($d['status'],['rejected','suspended'],true) && trim((string)($d['note']??''))==='') abort(422,'A reason is required for rejection or suspension.');
  $actor=$r->user(); $changed=0; $skipped=0;
  foreach(BusinessPartner::query()->whereIn('id',array_map('intval',$d['partner_ids']))->with('business')->get() as $partner){
   $from=(string)$partner->status; $to=(string)$d['status'];
   $allowed=(($from==='pending'&&in_array($to,['active','rejected'],true))||($from==='active'&&$to==='suspended')||($from==='suspended'&&$to==='active'));
   if(!$allowed){$skipped++;continue;}
   try{
    DB::transaction(function()use($partner,$actor,$from,$to,$d){
     $partner->updateOrFail(['status'=>$to]);
     $partner->events()->create(['actor_user_id'=>$actor->id,'action'=>'bulk_status_updated','status'=>$to,'note'=>$d['note']??null,'metadata'=>['from'=>$from,'to'=>$to,'bulk'=>true]]);
     if($to==='active'&&$partner->business)$partner->business->update(['status'=>'approved']);
    });
    $changed++;
   }catch(\\Throwable $e){report($e);$skipped++;}
  }
  $message="Bulk partner {$d['status']} completed: {$changed} changed, {$skipped} skipped.";
  return response()->json(['status'=>'completed','changed'=>$changed,'skipped'=>$skipped,'message'=>$message]);
 }

 public function bulkCommercialSettings(Request $r){
  $d=$r->validate([
   'partner_ids'=>['required','array','min:1','max:100'],
   'partner_ids.*'=>['integer','distinct','exists:business_partners,id'],
   'commission_rate_bps'=>['nullable','integer','min:0','max:10000'],
   'daily_limit_minor'=>['nullable','integer','min:0'],
   'monthly_limit_minor'=>['nullable','integer','min:0'],
   'parent_partner_id'=>['nullable','integer','exists:business_partners,id'],
   'settlement_mode'=>['nullable','in:wallet,manual'],
   'minimum_balance_minor'=>['nullable','integer','min:0'],
  ]);
  if(array_key_exists('daily_limit_minor',$d)&&array_key_exists('monthly_limit_minor',$d)&&$d['daily_limit_minor']!==null&&$d['monthly_limit_minor']!==null&&$d['daily_limit_minor']>$d['monthly_limit_minor']) abort(422,'Daily limit cannot exceed monthly limit.');
  $ids=array_map('intval',$d['partner_ids']);
  if(isset($d['parent_partner_id'])&&in_array((int)$d['parent_partner_id'],$ids,true)) abort(422,'A selected partner cannot be assigned as the parent of itself or another selected partner.');
  if($d['parent_partner_id']!==null){
   $parent=BusinessPartner::findOrFail((int)$d['parent_partner_id']);
   if($parent->status!=='active') abort(422,'Parent partner must be active.');
  }
  $fields=array_filter([
   'commission_rate_bps'=>$d['commission_rate_bps']??null,
   'daily_limit_minor'=>array_key_exists('daily_limit_minor',$d)?$d['daily_limit_minor']:null,
   'monthly_limit_minor'=>array_key_exists('monthly_limit_minor',$d)?$d['monthly_limit_minor']:null,
   'parent_partner_id'=>array_key_exists('parent_partner_id',$d)?$d['parent_partner_id']:null,
   'settlement_mode'=>$d['settlement_mode']??null,
   'minimum_balance_minor'=>array_key_exists('minimum_balance_minor',$d)?$d['minimum_balance_minor']:null,
  ],fn($v)=>$v!==null);
  if(!$fields) abort(422,'Provide at least one commercial setting to update.');
  $actor=$r->user(); $changed=0; $skipped=0;
  foreach(BusinessPartner::query()->whereIn('id',$ids)->get() as $partner){
   if(isset($d['parent_partner_id'])&&$d['parent_partner_id']!==null){
    $this->assertNoCycle($partner,(int)$d['parent_partner_id']);
   }
   try{
    DB::transaction(function()use($partner,$actor,$fields){
     $partner->updateOrFail($fields);
     $partner->events()->create(['actor_user_id'=>$actor->id,'action'=>'bulk_commercial_settings_updated','status'=>$partner->status,'metadata'=>['fields'=>array_keys($fields),'values'=>$fields,'bulk'=>true]]);
    });
    $changed++;
   }catch(\Throwable $e){report($e);$skipped++;}
  }
  return response()->json(['status'=>'completed','changed'=>$changed,'skipped'=>$skipped,'message'=>"Bulk commercial settings completed: {$changed} changed, {$skipped} skipped."]);
 }

 public function update(Request $r,BusinessPartner $partner){
  $d=$r->validate([
   'status'=>'sometimes|in:pending,active,suspended,rejected',
   'pricing_profile'=>'sometimes|string|max:100',
   'daily_limit_minor'=>'nullable|integer|min:0',
   'monthly_limit_minor'=>'nullable|integer|min:0',
   'commission_rate_bps'=>'sometimes|integer|min:0|max:10000',
   'parent_partner_id'=>'nullable|integer|exists:business_partners,id',
   'settlement_mode'=>'sometimes|in:wallet,manual',
   'minimum_balance_minor'=>'sometimes|integer|min:0',
   'service_rules'=>'nullable|array'
  ]);
  if(array_key_exists('parent_partner_id',$d)){
   $parentId=$d['parent_partner_id']===null?null:(int)$d['parent_partner_id'];
   if($parentId!==null){
    if($parentId===$partner->id) abort(422,'A partner cannot be its own parent.');
    $this->assertNoCycle($partner,$parentId);
    $parent=BusinessPartner::query()->findOrFail($parentId);
    if($parent->status!=='active') abort(422,'Parent partner must be active.');
    if($parent->type===$partner->type && $parent->id!==$partner->id) {
     // Same-type hierarchy is allowed; only cycles and inactive parents are prohibited.
    }
   }
  }
  if(isset($d['daily_limit_minor'],$d['monthly_limit_minor']) && $d['daily_limit_minor']!==null && $d['monthly_limit_minor']!==null && $d['daily_limit_minor']>$d['monthly_limit_minor']) abort(422,'Daily limit cannot exceed monthly limit.');
  $partner->update($d);
  $partner->events()->create(['actor_user_id'=>$r->user()->id,'action'=>'commercial_settings_updated','status'=>$partner->status,'metadata'=>['fields'=>array_keys($d),'values'=>$d]]);
  return response()->json(['partner'=>$partner->fresh(['user','business'])]);
 }

 public function hierarchy(BusinessPartner $partner){
  $children=BusinessPartner::query()->where('parent_partner_id',$partner->id)->with('user')->orderBy('type')->orderBy('id')->get();
  return response()->json(['parent_id'=>$partner->parent_partner_id,'children'=>$children]);
 }

 public function pricing(BusinessPartner $partner){
  return response()->json(['rules'=>DB::table('business_pricing_rules')->where('business_partner_id',$partner->id)->orderBy('service_key')->orderBy('product_key')->get()]);
 }

 public function savePricing(Request $r,BusinessPartner $partner){
  $d=$r->validate([
   'service_key'=>'required|string|max:100','product_key'=>'nullable|string|max:150',
   'rule_type'=>'required|in:markup,commission,discount','amount_minor'=>'nullable|integer|min:0',
   'rate_bps'=>'nullable|integer|min:0|max:10000','min_amount_minor'=>'nullable|integer|min:0',
   'max_amount_minor'=>'nullable|integer|min:0','enabled'=>'sometimes|boolean'
  ]);
  if(($d['amount_minor']??null)===null&&($d['rate_bps']??null)===null) abort(422,'Provide a fixed amount or rate.');
  if(isset($d['min_amount_minor'],$d['max_amount_minor'])&&$d['min_amount_minor']!==null&&$d['max_amount_minor']!==null&&$d['min_amount_minor']>$d['max_amount_minor']) abort(422,'Minimum amount cannot exceed maximum amount.');
  if(isset($d['amount_minor'],$d['rate_bps'])&&$d['amount_minor']!==null&&$d['rate_bps']!==null) abort(422,'Use either fixed amount or rate, not both.');
  $id=$r->input('id');
  if($id){
   $updated=DB::table('business_pricing_rules')->where('id',$id)->where('business_partner_id',$partner->id)->update(array_merge($d,['updated_at'=>now()]));
   if(!$updated) abort(404,'Pricing rule not found.');
  }else{
   DB::table('business_pricing_rules')->insert(array_merge($d,['business_partner_id'=>$partner->id,'created_at'=>now(),'updated_at'=>now()]));
  }
  $partner->events()->create(['actor_user_id'=>$r->user()->id,'action'=>'pricing_rule_saved','status'=>$partner->status,'metadata'=>['rule'=>$d,'id'=>$id]]);
  return response()->json(['rules'=>DB::table('business_pricing_rules')->where('business_partner_id',$partner->id)->get()]);
 }

 public function deletePricing(Request $r,BusinessPartner $partner,int $rule){
  $deleted=DB::table('business_pricing_rules')->where('id',$rule)->where('business_partner_id',$partner->id)->delete();
  if(!$deleted) abort(404,'Pricing rule not found.');
  $partner->events()->create(['actor_user_id'=>$r->user()->id,'action'=>'pricing_rule_deleted','status'=>$partner->status,'metadata'=>['rule_id'=>$rule]]);
  return response()->json(['ok'=>true]);
 }

 public function settlements(Request $r){
  $status=$r->validate(['status'=>'nullable|in:pending,settled,failed'])['status']??null;
  $q=BusinessCommissionSettlement::with(['sourcePartner.user','beneficiaryPartner.user'])->latest();
  if($status) $q->where('status',$status);
  return response()->json(['settlements'=>$q->paginate(100)]);
 }

 public function settle(Request $r,BusinessCommissionSettlement $settlement){
  if($settlement->status!=='pending') abort(422,'Only pending commission settlements can be settled.');
  $service=app(\Addons\BusinessAgentMerchantReseller\Services\BusinessCommissionService::class);
  $settled=$service->settle($settlement->id);
  $partner=BusinessPartner::find($settled->beneficiary_partner_id);
  $partner?->events()->create(['actor_user_id'=>$r->user()->id,'action'=>'commission_settled','status'=>'settled','metadata'=>['settlement_id'=>$settled->id,'reference'=>$settled->settlement_reference,'amount_minor'=>$settled->amount_minor]]);
  return response()->json(['settlement'=>$settled]);
 }

 private function assertNoCycle(BusinessPartner $partner,int $parentId):void{
  $seen=[$partner->id=>true];$current=$parentId;
  for($i=0;$i<20;$i++){
   if(isset($seen[$current])) abort(422,'Partner hierarchy cycle detected.');
   $seen[$current]=true;
   $next=BusinessPartner::query()->whereKey($current)->value('parent_partner_id');
   if($next===null) return;
   $current=(int)$next;
  }
  abort(422,'Partner hierarchy is too deep.');
 }
}