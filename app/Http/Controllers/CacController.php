<?php
namespace App\Http\Controllers;

use App\Models\CacOrder;
use App\Models\CacServiceProduct;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\Cac\CacOrderService;

class CacController extends Controller
{
 public function index(){return Inertia::render('Cac/Index',['products'=>CacServiceProduct::where('enabled',true)->orderBy('name')->get(['id','identifier','name','service_type','description','currency','selling_price_minor','requirements'])]);}
 public function store(Request $request, CacOrderService $orders)
 {
  $data=$request->validate(['service_product_id'=>['required','integer','exists:cac_service_products,id'],'customer_name'=>['nullable','string','max:150'],'business_name'=>['nullable','string','max:255'],'company_type'=>['nullable','string','max:100'],'payload'=>['nullable','array']]);
  $product=CacServiceProduct::where('enabled',true)->findOrFail($data['service_product_id']);
  $payload=array_merge((array)($data['payload']??[]),array_filter(['customer_name'=>$data['customer_name']??null,'business_name'=>$data['business_name']??null,'company_type'=>$data['company_type']??null],fn($v)=>$v!==null));
  $order=$orders->create((int)$request->user()->id,$product,$payload,null);
  return redirect()->route('cac.orders.show',$order)->with('success','CAC application created. Complete the required documents for review.');
 }
 public function orders(Request $request){return Inertia::render('Cac/Orders',['orders'=>CacOrder::where('user_id',$request->user()->id)->with('product')->latest()->paginate(20)]);}
 public function show(Request $request,CacOrder $order){abort_unless((int)$order->user_id===(int)$request->user()->id,404);return Inertia::render('Cac/Order',['order'=>$order->load(['product','documents','statusHistory'])]);}
}