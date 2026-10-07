<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Semizzy\Addons\Social\Models\SocialAccountInventory;
use Semizzy\Addons\Social\Models\SocialNumberInventory;
use Semizzy\Addons\Social\Models\SocialServiceOrder;
use Semizzy\Addons\Social\Models\SocialNumberSms;
use Semizzy\Addons\Social\Services\SocialServicesService;

class AdminSocialServicesController extends Controller {
 public function index(){return Inertia::render('Admin/SocialServices',['accounts'=>SocialAccountInventory::latest()->paginate(30),'numbers'=>SocialNumberInventory::latest()->paginate(30),'orders'=>SocialServiceOrder::with('user')->latest()->paginate(30)]);}
 public function account(Request $r){$d=$r->validate(['platform'=>'required|string|max:60','title'=>'required|string|max:160','username'=>'nullable|string|max:160','country_code'=>'nullable|string|max:8','account_age_days'=>'nullable|integer|min:0','followers'=>'nullable|integer|min:0','niche'=>'nullable|string|max:120','description'=>'nullable|string','fulfillment_mode'=>'required|in:api,manual,api_or_manual','provider_reference'=>'nullable|string|max:255','price'=>'required|numeric|min:0','currency'=>'required|string|size:3','status'=>'required|in:available,reserved,sold,disabled','credentials'=>'nullable|array','metadata'=>'nullable|array']);$d['currency']=strtoupper($d['currency']);return response()->json(['data'=>SocialAccountInventory::create($d)],201);}
 public function number(Request $r){$d=$r->validate(['country_code'=>'required|string|max:8','country_name'=>'required|string|max:100','service_key'=>'nullable|string|max:120','phone_number'=>'required|string|max:64','fulfillment_mode'=>'required|in:api,manual,api_or_manual','provider_reference'=>'nullable|string|max:255','price'=>'required|numeric|min:0','currency'=>'required|string|size:3','status'=>'required|in:available,reserved,sold,disabled','expires_at'=>'nullable|date','metadata'=>'nullable|array']);$d['currency']=strtoupper($d['currency']);$d['phone_hash']=hash('sha256',preg_replace('/\D+/','',$d['phone_number']));return response()->json(['data'=>SocialNumberInventory::create($d)],201);}
 public function sms(Request $r,SocialServiceOrder $order,SocialServicesService $service){$d=$r->validate(['message'=>'required|string|max:10000','sender'=>'nullable|string|max:160','provider_message_id'=>'nullable|string|max:160','metadata'=>'nullable|array']);return response()->json(['data'=>$service->ingestSms($order,$d['message'],$d['sender']??null,$d['provider_message_id']??null,$d['metadata']??[])],201);}
}