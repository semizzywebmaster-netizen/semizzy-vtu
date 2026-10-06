<?php

namespace Semizzy\Addons\SimHosting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Semizzy\Addons\SimHosting\Models\SimHostingProduct;
use Semizzy\Addons\SimHosting\Models\SimHostingRental;
use Semizzy\Addons\SimHosting\Models\SimHostingNumber;

class SimHostingController extends Controller
{
    public function index(Request $request)
    {
        return inertia('SimHosting', [
            'products' => SimHostingProduct::where('active', true)->withCount(['numbers'=>fn($q)=>$q->where('status','available')])->get(),
            'rentals' => SimHostingRental::where('user_id',$request->user()->id)->with(['product','number'])->latest()->get(),
        ]);
    }

    public function apiIndex(Request $request)
    {
        return response()->json([
            'products'=>SimHostingProduct::where('active',true)->withCount(['numbers'=>fn($q)=>$q->where('status','available')])->get(),
            'rentals'=>SimHostingRental::where('user_id',$request->user()->id)->with(['product','number'])->latest()->get(),
        ]);
    }

    public function rent(Request $request)
    {
        $data=$request->validate([
            'product_key'=>'required|string|max:80',
            'rental_days'=>'required|integer|min:1|max:365',
            'idempotency_key'=>'required|string|max:128',
        ]);

        $product=SimHostingProduct::where('key',$data['product_key'])->where('active',true)->firstOrFail();
        $number=SimHostingNumber::where('sim_hosting_product_id',$product->id)->where('status','available')->lockForUpdate()->firstOrFail();

        $existing=SimHostingRental::where('idempotency_key',$data['idempotency_key'])->where('user_id',$request->user()->id)->first();
        if($existing){ return response()->json($existing->load(['product','number'])); }

        $rental=SimHostingRental::create([
            'user_id'=>$request->user()->id,'sim_hosting_product_id'=>$product->id,'sim_hosting_number_id'=>$number->id,
            'reference'=>'SIM-'.strtoupper(Str::random(12)),'currency'=>$product->currency,
            'amount_minor'=>$product->rental_price_minor,'rental_days'=>$data['rental_days'],
            'status'=>'pending','idempotency_key'=>$data['idempotency_key'],
        ]);

        $number->status='assigned'; $number->saveOrFail();
        return response()->json($rental->load(['product','number']),201);
    }
}