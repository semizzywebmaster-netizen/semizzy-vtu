<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Semizzy\Addons\Social\Models\SocialAccount;
use Semizzy\Addons\Social\Models\SocialVerificationRequest;
use Semizzy\Addons\Social\Services\SocialVerificationService;
class SocialAccountController extends Controller{
 public function index(Request $r){$accounts=SocialAccount::where('user_id',$r->user()->id)->latest()->get();return $r->expectsJson()?response()->json(['data'=>$accounts]):Inertia::render('Social/Accounts',['accounts'=>$accounts]);}
 public function store(Request $r){$d=$r->validate(['platform'=>['required','string','max:50'],'username'=>['required','string','max:160'],'account_reference'=>['nullable','string','max:255']]);$account=SocialAccount::create(['user_id'=>$r->user()->id,'platform'=>trim($d['platform']),'username'=>trim($d['username']),'account_reference'=>$d['account_reference']??null]);return response()->json(['data'=>$account],201);}
 public function verify(Request $r,SocialAccount $account,SocialVerificationService $service){abort_unless($account->user_id===$r->user()->id,404);$d=$r->validate(['method'=>['nullable','string','max:50'],'evidence'=>['nullable','string','max:5000']]);$request=$service->request($account,$r->user()->id,$d['method']??'manual',$d['evidence']??null);if(($d['method']??'manual')!=='manual')$request=$service->verify($request);return response()->json(['data'=>$request],202);}
 public function requery(Request $r,SocialAccount $account,SocialVerificationService $service){abort_unless($account->user_id===$r->user()->id,404);$request=$account->verificationRequests()->latest()->firstOrFail();return response()->json(['data'=>$service->requery($request)]);}
}