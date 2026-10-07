<?php

namespace Semizzy\Addons\Marketplace\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use RuntimeException;
use Semizzy\Addons\Marketplace\Models\MarketplaceOrder;
use Semizzy\Addons\Marketplace\Models\MarketplaceProduct;
use Semizzy\Addons\Marketplace\Services\MarketplaceOrderService;

final class MarketplaceController
{
    public function index()
    {
        return Inertia::render('Marketplace/Index', [
            'products' => MarketplaceProduct::query()->where('status', 'active')->latest()->paginate(24),
        ]);
    }

    public function admin()
    {
        return Inertia::render('Admin/Marketplace/Index', [
            'products' => MarketplaceProduct::query()->with('seller')->latest()->paginate(30),
            'orders' => MarketplaceOrder::query()->with(['buyer', 'seller', 'product'])->latest()->paginate(30),
        ]);
    }

    public function store(Request $request, MarketplaceOrderService $orders)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:marketplace_products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);
        try {
            $order = $orders->create((int) $request->user()->id, $data);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'order' => $order], 201);
    }

    public function pay(Request $request, MarketplaceOrder $order, MarketplaceOrderService $orders)
    {
        try {
            $order = $orders->pay($order, (int) $request->user()->id);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'order' => $order]);
    }

    public function productStore(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:180'],
            'description' => ['nullable','string','max:10000'],
            'category' => ['required','string','max:100'],
            'price_minor' => ['required','regex:/^[1-9]\d*$/','max:30'],
            'stock_quantity' => ['required','regex:/^\d+$/','max:30'],
            'currency' => ['required','string','size:3'],
        ]);
        $data['currency'] = strtoupper($data['currency']);
        $data['slug'] = Str::slug($data['name']).'-'.strtolower(Str::random(8));
        $data['seller_id'] = (int) $request->user()->id;
        $data['status'] = 'active';
        return response()->json(['success'=>true,'product'=>MarketplaceProduct::create($data)],201);
    }

    public function productUpdate(Request $request, MarketplaceProduct $product)
    {
        if ((int)$product->seller_id !== (int)$request->user()->id && !$request->user()->hasPermission('marketplace.manage')) abort(403);
        $data = $request->validate([
            'name'=>['required','string','max:180'],
            'description'=>['nullable','string','max:10000'],
            'category'=>['required','string','max:100'],
            'price_minor'=>['required','regex:/^[1-9]\d*$/','max:30'],
            'stock_quantity'=>['required','regex:/^\d+$/','max:30'],
            'currency'=>['required','string','size:3'],
            'status'=>['required',Rule::in(['draft','active','paused','archived'])],
        ]);
        $data['currency']=strtoupper($data['currency']);
        $product->forceFill($data)->save();
        return response()->json(['success'=>true,'product'=>$product->fresh()]);
    }

    public function productDestroy(Request $request, MarketplaceProduct $product)
    {
        if ((int)$product->seller_id !== (int)$request->user()->id && !$request->user()->hasPermission('marketplace.manage')) abort(403);
        if (MarketplaceOrder::query()->where('product_id',$product->id)->exists()) {
            return response()->json(['success'=>false,'message'=>'Products with orders cannot be deleted; archive them instead.'],422);
        }
        $product->delete();
        return response()->json(['success'=>true]);
    }
}
