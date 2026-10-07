<?php
namespace Semizzy\Addons\Marketplace\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Semizzy\Addons\Marketplace\Models\MarketplaceProduct;
use Semizzy\Addons\Marketplace\Models\MarketplaceOrder;
class MarketplaceController {
 public function index() {
  return Inertia::render('Marketplace/Index',['products'=>MarketplaceProduct::query()->where('status','active')->latest()->paginate(24)]);
 }
 public function admin() {
  return Inertia::render('Admin/Marketplace/Index',[
   'products'=>MarketplaceProduct::query()->with('seller')->latest()->paginate(30),
   'orders'=>MarketplaceOrder::query()->with(['buyer','seller','product'])->latest()->paginate(30),
  ]);
 }
 public function store(Request $request) {
  $data=$request->validate([
   'product_id'=>['required','integer','exists:marketplace_products,id'],
   'quantity'=>['required','integer','min:1','max:1000000'],
   'note'=>['nullable','string','max:1000'],
   'idempotency_key'=>['nullable','string','max:120'],
  ]);
  $order=DB::transaction(function() use($request,$data){
   $product=MarketplaceProduct::query()->lockForUpdate()->findOrFail($data['product_id']);
   if($product->status!=='active') abort(422,'This product is not available.');
   if((int)$product->stock_quantity<(int)$data['quantity']) abort(422,'Insufficient product stock.');
   if((int)$product->seller_id===(int)$request->user()->id) abort(422,'You cannot purchase your own product.');
   $key=$data['idempotency_key']??null;
   if($key){
    $existing=MarketplaceOrder::query()->where('buyer_id',$request->user()->id)->where('idempotency_key',$key)->lockForUpdate()->first();
    if($existing) return $existing;
   }
   $total=bcmul((string)$product->price_minor,(string)$data['quantity'],0);
   $order=MarketplaceOrder::create([
    'reference'=>'MKT-'.strtoupper(Str::random(18)),
    'buyer_id'=>$request->user()->id,'seller_id'=>$product->seller_id,'product_id'=>$product->id,
    'idempotency_key'=>$key,'quantity'=>(string)$data['quantity'],'unit_price_minor'=>(string)$product->price_minor,
    'total_minor'=>$total,'currency'=>strtoupper($product->currency),'status'=>'pending','note'=>$data['note']??null,
   ]);
   return $order;
  });
  return response()->json(['success'=>true,'order'=>$order],201);
 }
}