<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Semizzy\Addons\Smm\Models\SmmOrder;
use Semizzy\Addons\Smm\Models\SmmService;
use Semizzy\Addons\Smm\Services\SmmOrderService;

final class SmmController extends Controller
{
 public function index(){return Inertia::render('SMM/Index',['services'=>SmmService::where('active',true)->with('category')->orderBy('name')->get()]);}
 public function store(Request $r,SmmOrderService $service){
  $d=$r->validate(['service_id'=>['required','integer','exists:smm_services,id'],'quantity'=>['required','integer','min:1'],'target'=>['required','string','max:500'],'idempotency_key'=>['required','string','max:120']]);
  $order=$service->create($r->user()->id,$d['service_id'],$d['quantity'],$d['target'],$d['idempotency_key']);
  return response()->json(['data'=>$order->fresh('service')],$order->wasRecentlyCreated?201:200);
 }
 public function history(Request $r){return response()->json(['data'=>SmmOrder::with('service')->where('user_id',$r->user()->id)->latest()->paginate(25)]);}
 public function requery(Request $r,SmmOrder $order){abort_unless((int)$order->user_id===(int)$r->user()->id,404);return response()->json(['data'=>$order->fresh('service')]);}
 public function cancel(Request $r,SmmOrder $order){abort_unless((int)$order->user_id===(int)$r->user()->id,404);if(!in_array(strtolower($order->status),['pending','processing','accepted'],true))return response()->json(['message'=>'Order cannot be cancelled in its current state.'],422);$order->status='cancel_requested';$order->save();return response()->json(['data'=>$order->fresh('service')]);}
}