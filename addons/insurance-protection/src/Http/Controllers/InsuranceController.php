<?php
namespace Addons\InsuranceProtection\Http\Controllers;
use Addons\InsuranceProtection\Models\{InsuranceProduct,InsurancePolicy,InsuranceClaim};
use Addons\InsuranceProtection\Services\InsuranceService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
class InsuranceController {
 public function index(Request $r){return Inertia::render('Insurance',['products'=>InsuranceProduct::where('active',true)->with('provider')->orderBy('category')->orderBy('name')->get(),'policies'=>InsurancePolicy::where('user_id',$r->user()->id)->with('product')->latest()->get()]);}
 public function products(){return response()->json(['products'=>InsuranceProduct::where('active',true)->with('provider')->orderBy('category')->orderBy('name')->get()]);}
 public function purchase(Request $r,InsuranceService $s){$d=$r->validate(['product_id'=>['required','integer','exists:insurance_products,id'],'holder'=>['required','array'],'holder.name'=>['nullable','string','max:191'],'holder.phone'=>['nullable','string','max:40'],'holder.email'=>['nullable','email','max:191'],'idempotency_key'=>['nullable','string','max:191']]);$p=InsuranceProduct::findOrFail($d['product_id']);$d['holder']['name']=$d['holder']['name']??$r->user()->name;$key=$d['idempotency_key']?:'insurance:purchase:'.$r->user()->id.':'.Str::uuid();return response()->json(['policy'=>$s->purchase($r->user(),$p,$d['holder'],$key)],201);}
 public function policies(Request $r){return response()->json(['policies'=>InsurancePolicy::where('user_id',$r->user()->id)->with('product')->latest()->get()]);}
 public function requery(Request $r,InsurancePolicy $policy,InsuranceService $s){return response()->json(['policy'=>$s->requery($r->user(),$policy)]);}
 public function cancel(Request $r,InsurancePolicy $policy,InsuranceService $s){$d=$r->validate(['reason'=>['required','string','max:500']]);return response()->json(['policy'=>$s->cancel($r->user(),$policy,$d['reason'])]);}
 public function renew(Request $r,InsurancePolicy $policy,InsuranceService $s){$key='insurance:renew:'.$policy->id.':'.($r->input('idempotency_key')?:Str::uuid());return response()->json(['policy'=>$s->renew($r->user(),$policy,$key)]);}
 public function claim(Request $r,InsurancePolicy $policy,InsuranceService $s){$d=$r->validate(['claim_type'=>['nullable','string','max:100'],'amount_minor'=>['nullable','integer','min:1'],'description'=>['required','string','max:10000'],'documents'=>['nullable','array']]);return response()->json(['claim'=>$s->claim($r->user(),$policy,$d)],201);}
 public function claims(Request $r){return response()->json(['claims'=>InsuranceClaim::where('user_id',$r->user()->id)->with('policy.product')->latest()->get()]);}
}