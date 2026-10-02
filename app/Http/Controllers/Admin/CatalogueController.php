<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Http\\Controllers\\Controller;
use App\\Models\\ProviderServiceProduct;
use App\\Models\\Service;
use App\\Models\\ServiceCategory;
use App\\Models\\ServiceProduct;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\DB;
use Inertia\\Inertia;
use Inertia\\Response;

class CatalogueController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Catalogue', [
            'categories' => ServiceCategory::query()->with(['services.products'])->orderBy('sort_order')->get(),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data=$request->validate([
            'key'=>'required|string|max:100|alpha_dash|unique:service_categories,key',
            'name'=>'required|string|max:160',
            'description'=>'nullable|string|max:5000',
            'sort_order'=>'nullable|integer|min:0|max:100000',
            'enabled'=>'nullable|boolean',
        ]);
        ServiceCategory::create($data+['enabled'=>$data['enabled']??true]);
        return back()->with('success','Service category created.');
    }

    public function storeService(Request $request): RedirectResponse
    {
        $data=$request->validate([
            'category_id'=>'required|integer|exists:service_categories,id',
            'key'=>'required|string|max:100|alpha_dash|unique:services,key',
            'name'=>'required|string|max:160',
            'description'=>'nullable|string|max:5000',
            'metadata'=>'nullable|array',
            'enabled'=>'nullable|boolean',
        ]);
        Service::create($data+['enabled'=>$data['enabled']??true]);
        return back()->with('success','Service created.');
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $data=$request->validate([
            'service_id'=>'required|integer|exists:services,id',
            'key'=>'required|string|max:120',
            'name'=>'required|string|max:200',
            'currency'=>'required|string|size:3',
            'metadata'=>'nullable|array',
            'enabled'=>'nullable|boolean',
        ]);
        if(ServiceProduct::query()->where('service_id',$data['service_id'])->where('key',$data['key'])->exists())
            return back()->with('error','A product with this key already exists under this service.');
        ServiceProduct::create($data+['currency'=>strtoupper($data['currency']),'enabled'=>$data['enabled']??false]);
        return back()->with('success','Service product created.');
    }

    public function disableProduct(ServiceProduct $product): RedirectResponse
    {
        DB::transaction(fn()=>ProviderServiceProduct::query()->where('service_product_id',$product->id)->update(['enabled'=>false]));
        $product->update(['enabled'=>false]);
        return back()->with('success','Product and provider mappings disabled.');
    }
}
