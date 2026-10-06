<?php

namespace Semizzy\Addons\SimHosting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use Illuminate\Http\Request;
use Semizzy\Addons\SimHosting\Models\SimHostingNumber;
use Semizzy\Addons\SimHosting\Models\SimHostingProduct;
use Semizzy\Addons\SimHosting\Models\SimHostingRental;

class AdminSimHostingController extends Controller
{
    public function index()
    {
        return inertia('Admin/SimHosting', [
            'products'=>SimHostingProduct::with(['provider:id,identifier,display_name'])->withCount('numbers')->latest()->get(),
            'availableNumbers'=>SimHostingNumber::where('status','available')->count(),
            'rentals'=>SimHostingRental::with(['product','number','user'])->latest()->paginate(25),
            'providers'=>ApiProvider::query()->where('enabled',true)->where('paused',false)->whereIn('integration_status',['live_verified','sandbox_verified'])->whereIn('verification_status',['live_verified','sandbox_verified'])->orderBy('priority')->get(['id','identifier','display_name']),
        ]);
    }

    public function saveProduct(Request $request, ?int $id = null)
    {
        $data=$request->validate([
            'key'=>'required|string|max:80','name'=>'required|string|max:160','country'=>'required|string|size:2',
            'network'=>'nullable|string|max:80','currency'=>'required|string|size:3','rental_price_minor'=>'required|integer|min:0',
            'rental_days'=>'required|integer|min:1|max:365','renewal_price_minor'=>'nullable|integer|min:0',
            'max_rental_days'=>'nullable|integer|min:1|max:365','active'=>'boolean','provider_id'=>'nullable|integer|exists:api_providers,id',
        ]);
        $product=$id?SimHostingProduct::findOrFail($id):new SimHostingProduct();
        $product->fill($data); $product->saveOrFail(); return back();
    }

    public function toggleProduct(SimHostingProduct $product){$product->update(['active'=>!$product->active]);return back();}

    public function saveNumber(Request $request, ?int $id = null)
    {
        $data=$request->validate([
            'sim_hosting_product_id'=>'required|integer|exists:sim_hosting_products,id','number'=>'required|string|max:80',
            'country'=>'required|string|size:2','network'=>'nullable|string|max:80','provider_reference'=>'nullable|string|max:160',
            'provider_status'=>'nullable|string|max:40','status'=>'required|in:available,assigned,blocked,maintenance',
        ]);
        $number=$id?SimHostingNumber::findOrFail($id):new SimHostingNumber();
        $number->fill($data); $number->saveOrFail(); return back();
    }

    public function toggleNumber(SimHostingNumber $number){$number->status=$number->status==='maintenance'?'available':'maintenance';$number->saveOrFail();return back();}
}
