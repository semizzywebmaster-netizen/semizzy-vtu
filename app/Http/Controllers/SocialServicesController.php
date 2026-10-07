<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Semizzy\Addons\Social\Models\SocialAccountInventory;
use Semizzy\Addons\Social\Models\SocialNumberInventory;
use Semizzy\Addons\Social\Models\SocialServiceOrder;
use Semizzy\Addons\Social\Services\SocialServicesService;

class SocialServicesController extends Controller {
 public function index(Request $r){
  $accounts=SocialAccountInventory::where('status','available')->latest()->paginate(20,['id','platform','title','country_code','account_age_days','followers','niche','description','price','currency','fulfillment_mode']);
  $numbers=SocialNumberInventory::where('status','available')->latest()->paginate(20,['id','country_code','country_name','service_key','price','currency','fulfillment_mode']);
  $orders=SocialServiceOrder::where('user_id',$r->user()->id)->latest()->paginate(20);
  return Inertia::render('SocialServices/Index',compact('accounts','numbers','orders'));
 }
 public function buyAccount(Request $r,SocialAccountInventory $inventory,SocialServicesService $service){$order=$service->createAccountOrder($r->user()->id,$inventory->id);return response()->json(['data'=>$order],201);}
 public function buyNumber(Request $r,SocialNumberInventory $inventory,SocialServicesService $service){$order=$service->createNumberOrder($r->user()->id,$inventory->id);return response()->json(['data'=>$order],201);}
 public function pay(Request $r,SocialServiceOrder $order,SocialServicesService $service){return response()->json(['data'=>$service->payFromWallet($r->user(),$order)]);}
 public function sms(Request $r,SocialServiceOrder $order){
  abort_unless($order->user_id===$r->user()->id && $order->order_type==='number',404); if(!app(SocialServicesService::class)->canReceiveSms($order)) abort(410,'This verification number is expired or inactive.');
  $messages=$order->sms()->latest('received_at')->paginate(50);
  if($r->expectsJson()) return response()->json(['data'=>$messages]);
  return Inertia::render('SocialServices/SmsInbox',['order'=>$order->only(['id','reference','status','expires_at']),'messages'=>$messages]);
 }
}