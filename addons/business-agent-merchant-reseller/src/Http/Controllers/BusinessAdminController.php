<?php
namespace Addons\BusinessAgentMerchantReseller\Http\Controllers;
use Addons\BusinessAgentMerchantReseller\Models\{BusinessProfile,BusinessPartner};
use Addons\BusinessAgentMerchantReseller\Services\BusinessPartnerService;
use Illuminate\Http\Request;
use Inertia\Inertia;
class BusinessAdminController{
 public function page(){return Inertia::render('BusinessAdmin');}
 public function partners(){return response()->json(['partners'=>BusinessPartner::with(['user','business'])->latest()->paginate(100)]);}
 public function approve(Request $r,BusinessPartner $partner,BusinessPartnerService $s){$d=$r->validate(['approved'=>'required|boolean','note'=>'nullable|string|max:1000']);return response()->json(['partner'=>$s->approve($partner,$r->user(),$d['approved'],$d['note']??'')]);}
 public function update(Request $r,BusinessPartner $partner){$d=$r->validate(['status'=>'sometimes|in:pending,active,suspended,rejected','pricing_profile'=>'sometimes|string|max:100','daily_limit_minor'=>'nullable|integer|min:0','monthly_limit_minor'=>'nullable|integer|min:0','commission_rate_bps'=>'sometimes|integer|min:0|max:10000']);$partner->update($d);return response()->json(['partner'=>$partner->fresh()]);}
}
