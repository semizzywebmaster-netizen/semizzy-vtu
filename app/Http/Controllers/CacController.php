<?php
namespace App\Http\Controllers;

use App\Models\CacOrder;
use App\Models\CacServiceProduct;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CacController extends Controller
{
 public function index(){return Inertia::render('Cac/Index',['products'=>CacServiceProduct::where('enabled',true)->orderBy('name')->get(['id','identifier','name','service_type','description','currency','selling_price_minor','requirements'])]);}
 public function orders(Request $request){return Inertia::render('Cac/Orders',['orders'=>CacOrder::where('user_id',$request->user()->id)->with('product')->latest()->paginate(20)]);}
 public function show(Request $request,CacOrder $order){abort_unless((int)$order->user_id===(int)$request->user()->id,404);return Inertia::render('Cac/Order',['order'=>$order->load(['product','documents','statusHistory'])]);}
}