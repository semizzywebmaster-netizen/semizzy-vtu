<?php

namespace Semizzy\Addons\SimHosting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Semizzy\Addons\SimHosting\Models\SimHostingProduct;
use Semizzy\Addons\SimHosting\Models\SimHostingRental;
use Semizzy\Addons\SimHosting\Services\SimHostingService;

class SimHostingController extends Controller
{
    public function index(Request $request){return inertia('SimHosting',['products'=>SimHostingProduct::where('active',true)->withCount(['numbers'=>fn($q)=>$q->where('status','available')])->get(),'rentals'=>SimHostingRental::where('user_id',$request->user()->id)->with(['product','number'])->latest()->get()]);}
    public function apiIndex(Request $request){return response()->json(['products'=>SimHostingProduct::where('active',true)->withCount(['numbers'=>fn($q)=>$q->where('status','available')])->get(),'rentals'=>SimHostingRental::where('user_id',$request->user()->id)->with(['product','number'])->latest()->get()]);}
    public function rent(Request $request,SimHostingService $service){$data=$request->validate(['product_key'=>'required|string|max:80','rental_days'=>'required|integer|min:1|max:365','idempotency_key'=>'required|string|max:128']);return response()->json($service->rent($request->user()->id,$data),201);}
    public function renew(Request $request,string $reference,SimHostingService $service){$data=$request->validate(['idempotency_key'=>'required|string|max:128']);$r=SimHostingRental::where('reference',$reference)->firstOrFail();return response()->json($service->renew($r,$request->user()->id,$data['idempotency_key']));}
}