<?php
namespace Semizzy\Addons\GiftCards\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Semizzy\Addons\GiftCards\Models\GiftCardProduct;
use Semizzy\Addons\GiftCards\Services\GiftCardPurchaseService;

class GiftCardsController extends Controller
{
 public function __construct(private GiftCardPurchaseService $purchases){}
 public function index(){return Inertia::render('GiftCards/Index',['products'=>GiftCardProduct::where('enabled',true)->orderBy('brand')->orderBy('name')->get()]);}
 public function apiProducts(){return response()->json(['data'=>GiftCardProduct::where('enabled',true)->orderBy('brand')->orderBy('name')->get()]);}
 public function purchase(Request $request){
  $data=$request->validate(['product_id'=>'required|integer','amount'=>'required|numeric|min:0.01','currency'=>'required|string|max:8','idempotency_key'=>'required|string|max:100','metadata'=>'nullable|array']);
  $order=$this->purchases->purchase($request->user()->id,$data['product_id'],(string)$data['amount'],$data['currency'],$data['idempotency_key'],$data['metadata']??[]);
  return $request->expectsJson()?response()->json(['data'=>$order],$order->status==='failed'?422:201):back()->with('success','Gift-card purchase submitted.');
 }
 public function orders(Request $request){return Inertia::render('GiftCards/Orders',['orders'=>$request->user()->giftCardOrders()->latest()->paginate(20)]);}
 public function apiOrders(Request $request){return response()->json(['data'=>$request->user()->giftCardOrders()->latest()->paginate(20)]);}
 public function requery(){return back()->with('success','Gift-card requery queued for provider processing.');}
}