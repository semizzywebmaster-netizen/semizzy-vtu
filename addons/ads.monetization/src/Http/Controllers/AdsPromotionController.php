<?php

namespace Semizzy\\Addons\\Ads\\Http\\Controllers;

use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Validation\\Rule;
use Inertia\\Inertia;
use Inertia\\Response;

class AdsPromotionController
{
 public function index(Request $request): Response
 {
  $userId = (int) $request->user()->id;
  $packages = DB::table('ad_promotion_packages')->where('is_active', true)->orderBy('price_minor')->get();
  $promotions = DB::table('ad_promotions')->where('advertiser_id', $userId)->orderByDesc('created_at')->limit(100)->get();
  return Inertia::render('Ads/Promotions/Index', [
   'packages' => $packages,
   'promotions' => $promotions,
   'currency' => 'NGN',
  ]);
 }

 public function store(Request $request)
 {
  $data = $request->validate([
   'package_id' => ['required','integer','exists:ad_promotion_packages,id'],
   'target_type' => ['required', Rule::in(['marketplace_listing'])],
   'target_id' => ['required','integer','min:1'],
   'target_label' => ['nullable','string','max:180'],
   'advertiser_note' => ['nullable','string','max:1000'],
  ]);

  $package = DB::table('ad_promotion_packages')->where('id',$data['package_id'])->where('is_active',true)->first();
  if (!$package) return response()->json(['success'=>false,'message'=>'This promotion package is not available.'],422);

  // Do not mark the target as boosted or activate the promotion here. Ownership/listing
  // validation and payment settlement must be completed by their respective integrations.
  $id = DB::table('ad_promotions')->insertGetId([
   'advertiser_id'=>$request->user()->id,
   'package_id'=>$package->id,
   'target_type'=>$data['target_type'],
   'target_id'=>$data['target_id'],
   'target_label'=>$data['target_label'] ?? null,
   'price_minor'=>$package->price_minor,
   'currency'=>$package->currency,
   'status'=>'pending_review',
   'payment_status'=>'unpaid',
   'advertiser_note'=>$data['advertiser_note'] ?? null,
   'created_at'=>now(),
   'updated_at'=>now(),
  ]);
  return response()->json(['success'=>true,'message'=>'Promotion request submitted. It will not run until listing ownership is verified, it is approved, and payment is confirmed.','id'=>$id],201);
 }

 public function adminIndex(): Response
 {
  return Inertia::render('Admin/Ads/Promotions', [
   'promotions'=>DB::table('ad_promotions as p')
    ->leftJoin('users as u','u.id','=','p.advertiser_id')
    ->leftJoin('ad_promotion_packages as ap','ap.id','=','p.package_id')
    ->select('p.*','u.name as advertiser_name','ap.name as package_name')
    ->orderByRaw("CASE WHEN p.status = 'pending_review' THEN 0 ELSE 1 END")
    ->orderByDesc('p.created_at')->limit(200)->get(),
   'packages'=>DB::table('ad_promotion_packages')->orderBy('price_minor')->get(),
  ]);
 }

 public function storePackage(Request $request)
 {
  $data=$request->validate([
   'key'=>['required','alpha_dash','max:80','unique:ad_promotion_packages,key'],
   'name'=>['required','string','max:140'],
   'description'=>['nullable','string','max:1000'],
   'duration_days'=>['required','integer','min:1','max:365'],
   'price_minor'=>['required','integer','min:1','max:1000000000'],
   'currency'=>['required',Rule::in(['NGN'])],
   'priority_weight'=>['required','integer','min:1','max:100'],
   'is_active'=>['required','boolean'],
  ]);
  DB::table('ad_promotion_packages')->insert($data+['created_at'=>now(),'updated_at'=>now()]);
  return response()->json(['success'=>true,'message'=>'Promotion package created.']);
 }

 public function review(Request $request, int $promotion)
 {
  $data=$request->validate([
   'decision'=>['required',Rule::in(['approve','reject','pause'])],
   'admin_note'=>['required','string','min:5','max:2000'],
  ]);
  $row=DB::table('ad_promotions')->where('id',$promotion)->first();
  if(!$row) abort(404);
  if($data['decision']==='approve' && $row->payment_status!=='paid') {
   return response()->json(['success'=>false,'message'=>'Approval cannot activate a boost before payment is confirmed. The request can only be pre-approved after the payment integration is implemented.'],422);
  }
  $status=$data['decision']==='approve'?'approved':($data['decision']==='reject'?'rejected':'paused');
  DB::table('ad_promotions')->where('id',$promotion)->update([
   'status'=>$status,'admin_note'=>$data['admin_note'],'reviewed_by'=>$request->user()->id,
   'reviewed_at'=>now(),'updated_at'=>now(),
  ]);
  return response()->json(['success'=>true,'message'=>'Promotion review saved.']);
 }
}
