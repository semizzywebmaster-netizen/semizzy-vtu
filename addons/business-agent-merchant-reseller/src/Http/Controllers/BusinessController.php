<?php
namespace Addons\BusinessAgentMerchantReseller\Http\Controllers;
use Addons\BusinessAgentMerchantReseller\Models\{BusinessProfile,BusinessPartner};
use Addons\BusinessAgentMerchantReseller\Services\BusinessPartnerService;
use Illuminate\Http\Request;
use Inertia\Inertia;
class BusinessController{
 public function index(Request $r){return Inertia::render('BusinessPartner',['business'=>BusinessProfile::where('user_id',$r->user()->id)->first(),'partners'=>BusinessPartner::where('user_id',$r->user()->id)->latest()->get()]);}
 public function apply(Request $r,BusinessPartnerService $s){$d=$r->validate(['type'=>'required|in:agent,merchant,reseller','business_name'=>'required|string|max:191','business_type'=>'nullable|string|max:100','registration_number'=>'nullable|string|max:100','contact_phone'=>'nullable|string|max:40','contact_email'=>'nullable|email|max:191']);return response()->json(['partner'=>$s->apply($r->user(),$d,$d['type'])],201);}
 public function partners(Request $r){return response()->json(['partners'=>BusinessPartner::where('user_id',$r->user()->id)->latest()->get()]);}
}
