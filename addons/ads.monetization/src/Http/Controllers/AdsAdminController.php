<?php

namespace Semizzy\Addons\Ads\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdsAdminController
{
 public function index(): Response
 {
  $campaigns = DB::table('ad_campaigns as c')->leftJoin('users as u','u.id','=','c.advertiser_id')
   ->select('c.*','u.name as advertiser_name')
   ->orderByRaw("CASE WHEN c.review_status = 'pending' THEN 0 ELSE 1 END")
   ->orderByDesc('c.created_at')->limit(100)->get();
  return Inertia::render('Admin/Ads/Index', [
   'campaigns'=>$campaigns,
   'placements'=>DB::table('ad_placements')->orderBy('surface')->orderBy('name')->get(),
   'adTypes'=>DB::table('ad_types')->orderBy('name')->get(),
   'stats'=>[
    'campaigns'=>DB::table('ad_campaigns')->count(),
    'pending_review'=>DB::table('ad_campaigns')->where('review_status','pending')->count(),
    'active_campaigns'=>DB::table('ad_campaigns')->where('status','active')->where('review_status','approved')->count(),
    'impressions'=>DB::table('ad_events')->where('event_type','impression')->count(),
    'clicks'=>DB::table('ad_events')->where('event_type','click')->count(),
    'spend_minor'=>(string) DB::table('ad_campaigns')->sum('spent_minor'),
   ],
  ]);
 }

 public function review(Request $request, int $campaign)
 {
  $data=$request->validate(['decision'=>['required',Rule::in(['approve','reject','pause','resume'])],'admin_note'=>['required','string','min:5','max:2000']]);
  $row=DB::table('ad_campaigns')->where('id',$campaign)->first();
  if(!$row) abort(404);
  $now=now();
  if($data['decision']==='approve') $changes=['review_status'=>'approved','status'=>'paused'];
  elseif($data['decision']==='reject') $changes=['review_status'=>'rejected','status'=>'rejected'];
  elseif($data['decision']==='pause') $changes=['status'=>'paused'];
  else {
   if($row->review_status!=='approved') return response()->json(['success'=>false,'message'=>'Only approved campaigns can be resumed.'],422);
   $changes=['status'=>'active'];
  }
  DB::table('ad_campaigns')->where('id',$campaign)->update($changes+['admin_note'=>$data['admin_note'],'reviewed_by'=>$request->user()->id,'reviewed_at'=>$now,'updated_at'=>$now]);
  return response()->json(['success'=>true,'message'=>'Campaign moderation decision saved.']);
 }

 public function storePlacement(Request $request)
 {
  $data=$request->validate([
   'key'=>['required','alpha_dash','max:100','unique:ad_placements,key'],
   'name'=>['required','string','max:160'],'surface'=>['required','string','max:80'],
   'format'=>['required','string','max:100','exists:ad_types,key'],
   'description'=>['nullable','string','max:1000'],'is_active'=>['required','boolean'],
  ]);
  $type=DB::table('ad_types')->where('key',$data['format'])->first();
  if(!$type || !$type->is_active) return response()->json(['success'=>false,'message'=>'Choose an enabled ad type.'],422);
  DB::table('ad_placements')->insert($data+['created_at'=>now(),'updated_at'=>now()]);
  return response()->json(['success'=>true,'message'=>'Ad placement created.']);
 }

 public function updatePlacement(Request $request, int $placement)
 {
  $data=$request->validate([
   'name'=>['required','string','max:160'],'surface'=>['required','string','max:80'],
   'format'=>['required','string','max:100','exists:ad_types,key'],
   'description'=>['nullable','string','max:1000'],'is_active'=>['required','boolean'],
  ]);
  $type=DB::table('ad_types')->where('key',$data['format'])->first();
  if(!$type || !$type->is_active) return response()->json(['success'=>false,'message'=>'Choose an enabled ad type.'],422);
  $updated=DB::table('ad_placements')->where('id',$placement)->update($data+['updated_at'=>now()]);
  if(!$updated) abort(404);
  return response()->json(['success'=>true,'message'=>'Ad placement updated.']);
 }

 public function adTypes(): Response
 {
  return Inertia::render('Admin/Ads/Types',['adTypes'=>DB::table('ad_types')->orderByDesc('is_system')->orderBy('name')->get()]);
 }

 public function storeAdType(Request $request)
 {
  $data=$request->validate([
   'key'=>['required','alpha_dash','max:100','unique:ad_types,key'],
   'name'=>['required','string','max:160'],'description'=>['nullable','string','max:2000'],
   'format_family'=>['required',Rule::in(['native','banner','listing','video','message','external','custom'])],
   'pricing_models'=>['required','array','min:1'],'pricing_models.*'=>[Rule::in(['cpc','cpm','flat','cpa','free'])],
   'eligible_surfaces'=>['nullable','array'],'eligible_surfaces.*'=>['string','max:80'],
   'requires_creative'=>['required','boolean'],'supports_targeting'=>['required','boolean'],
   'requires_integration'=>['required','boolean'],'integration_key'=>['nullable','required_if:requires_integration,1','string','max:120'],
  ]);
  $data['is_system']=false;
  $data['is_active']=false;
  $data['created_at']=now(); $data['updated_at']=now();
  DB::table('ad_types')->insert($data);
  return response()->json(['success'=>true,'message'=>'Ad type saved as disabled. Review its delivery integration before enabling it.']);
 }

 public function updateAdType(Request $request, int $adType)
 {
  $type=DB::table('ad_types')->where('id',$adType)->first();
  if(!$type) abort(404);
  $data=$request->validate([
   'name'=>['required','string','max:160'],'description'=>['nullable','string','max:2000'],
   'format_family'=>['required',Rule::in(['native','banner','listing','video','message','external','custom'])],
   'pricing_models'=>['required','array','min:1'],'pricing_models.*'=>[Rule::in(['cpc','cpm','flat','cpa','free'])],
   'eligible_surfaces'=>['nullable','array'],'eligible_surfaces.*'=>['string','max:80'],
   'requires_creative'=>['required','boolean'],'supports_targeting'=>['required','boolean'],
   'requires_integration'=>['required','boolean'],'integration_key'=>['nullable','required_if:requires_integration,1','string','max:120'],
   'is_active'=>['required','boolean'],
  ]);
  if($data['is_active'] && $data['requires_integration']) return response()->json(['success'=>false,'message'=>'This format requires a delivery integration. It cannot be enabled until that integration is implemented and verified.'],422);
  if($type->is_system && !$data['is_active']) {
   $used=DB::table('ad_placements')->where('format',$type->key)->where('is_active',true)->exists();
   if($used) return response()->json(['success'=>false,'message'=>'Disable its active placements before disabling this built-in type.'],422);
  }
  DB::table('ad_types')->where('id',$adType)->update($data+['updated_at'=>now()]);
  return response()->json(['success'=>true,'message'=>'Ad type updated.']);
 }


 public function sellerPromotions(Request $request): Response
 {
  $userId=(int)$request->user()->id;
  return Inertia::render('Ads/Promotions/Index',[
   'products'=>DB::table('marketplace_products')->where('seller_id',$userId)->where('status','active')->orderBy('name')->get(['id','name','category','price_minor','currency']),
   'packages'=>DB::table('ad_promotion_packages')->where('is_active',true)->orderBy('price_minor')->get(),
   'requests'=>DB::table('ad_promotions')->where('advertiser_id',$userId)->orderByDesc('created_at')->limit(100)->get(),
  ]);
 }

 public function submitPromotion(Request $request)
 {
  $data=$request->validate([
   'product_id'=>['required','integer','min:1','exists:marketplace_products,id'],
   'package_id'=>['required','integer','min:1','exists:ad_promotion_packages,id'],
   'advertiser_note'=>['nullable','string','max:2000'],
  ]);
  $product=DB::table('marketplace_products')->where('id',$data['product_id'])->where('seller_id',$request->user()->id)->where('status','active')->first();
  if(!$product) return response()->json(['success'=>false,'message'=>'You can only request a boost for your own active Marketplace listing.'],403);
  $package=DB::table('ad_promotion_packages')->where('id',$data['package_id'])->where('is_active',true)->first();
  if(!$package) return response()->json(['success'=>false,'message'=>'That promotion package is not available.'],422);
  $duplicate=DB::table('ad_promotions')->where('advertiser_id',$request->user()->id)->where('target_type','marketplace_product')->where('target_id',$product->id)->whereIn('status',['pending_review','approved','active','paused'])->exists();
  if($duplicate) return response()->json(['success'=>false,'message'=>'This listing already has a pending or non-expired promotion.'],422);
  DB::table('ad_promotions')->insert([
   'advertiser_id'=>$request->user()->id,'package_id'=>$package->id,'target_type'=>'marketplace_product','target_id'=>$product->id,
   'target_label'=>$product->name,'price_minor'=>$package->price_minor,'currency'=>$package->currency,
   'status'=>'pending_review','payment_status'=>'unpaid','advertiser_note'=>$data['advertiser_note']??null,
   'created_at'=>now(),'updated_at'=>now(),
  ]);
  return response()->json(['success'=>true,'message'=>'Boost request submitted for review. Payment is not collected by this request, and the boost will not run until payment settlement and approval are implemented.']);
 }

 public function updatePromotionPackage(Request $request, int $package)
 {
  $row=DB::table('ad_promotion_packages')->where('id',$package)->first();
  if(!$row) abort(404);
  $data=$request->validate([
   'name'=>['required','string','max:140'],'description'=>['nullable','string','max:1000'],
   'duration_days'=>['required','integer','min:1','max:365'],'price_minor'=>['required','integer','min:1','max:1000000000'],
   'priority_weight'=>['required','integer','min:1','max:100'],'is_active'=>['required','boolean'],
  ]);
  DB::table('ad_promotion_packages')->where('id',$package)->update($data+['updated_at'=>now()]);
  return response()->json(['success'=>true,'message'=>'Promotion package updated.']);
 }

 public function promotions(): Response
 {
  return Inertia::render('Admin/Ads/Promotions',[
   'promotions'=>DB::table('ad_promotions as p')->leftJoin('users as u','u.id','=','p.advertiser_id')->leftJoin('ad_promotion_packages as ap','ap.id','=','p.package_id')->select('p.*','u.name as advertiser_name','ap.name as package_name')->orderByDesc('p.created_at')->limit(200)->get(),
   'packages'=>DB::table('ad_promotion_packages')->orderBy('price_minor')->get(),
  ]);
 }

 public function createPromotionPackage(Request $request)
 {
  $data=$request->validate([
   'key'=>['required','alpha_dash','max:80','unique:ad_promotion_packages,key'],
   'name'=>['required','string','max:140'],'description'=>['nullable','string','max:1000'],
   'duration_days'=>['required','integer','min:1','max:365'],'price_minor'=>['required','integer','min:1','max:1000000000'],
   'currency'=>['required','in:NGN'],'priority_weight'=>['required','integer','min:1','max:100'],'is_active'=>['required','boolean'],
  ]);
  DB::table('ad_promotion_packages')->insert($data+['created_at'=>now(),'updated_at'=>now()]);
  return response()->json(['success'=>true,'message'=>'Promotion package created.']);
 }

 public function reviewPromotion(Request $request, int $promotion)
 {
  $data=$request->validate(['decision'=>['required',Rule::in(['approve','reject','pause'])],'admin_note'=>['required','string','min:5','max:2000']]);
  $row=DB::table('ad_promotions')->where('id',$promotion)->first();
  if(!$row) abort(404);
  if($data['decision']==='approve' && $row->payment_status!=='paid') return response()->json(['success'=>false,'message'=>'Payment must be confirmed before a boost can be approved.'],422);
  $status=$data['decision']==='approve'?'approved':($data['decision']==='reject'?'rejected':'paused');
  $changes=['status'=>$status,'admin_note'=>$data['admin_note'],'reviewed_by'=>$request->user()->id,'reviewed_at'=>now(),'updated_at'=>now()];
  if($data['decision']==='approve') {
   $package=DB::table('ad_promotion_packages')->where('id',$row->package_id)->first();
   if(!$package) return response()->json(['success'=>false,'message'=>'The promotion package no longer exists.'],422);
   $changes['starts_at']=now();
   $changes['ends_at']=now()->addDays((int)$package->duration_days);
  }
  DB::table('ad_promotions')->where('id',$promotion)->update($changes);
  return response()->json(['success'=>true,'message'=>'Promotion review saved.']);
 }
}
