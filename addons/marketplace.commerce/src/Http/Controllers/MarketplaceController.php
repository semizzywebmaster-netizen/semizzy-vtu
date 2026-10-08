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

    public function cancel(Request $request, MarketplaceOrder $order, MarketplaceOrderService $orders)
    {
        try {
            $order=$orders->cancel($order,(int)$request->user()->id);
        } catch (RuntimeException $e) {
            return response()->json(['success'=>false,'message'=>$e->getMessage()],422);
        }
        return response()->json(['success'=>true,'order'=>$order]);
    }

    public function refund(Request $request, MarketplaceOrder $order, MarketplaceOrderService $orders)
    {
        $isAdmin = $request->user()->hasPermission('marketplace.orders.manage');
        try {
            $order = $orders->refund($order, (int)$request->user()->id, $isAdmin);
        } catch (RuntimeException $e) {
            return response()->json(['success'=>false,'message'=>$e->getMessage()],422);
        }
        return response()->json(['success'=>true,'order'=>$order]);
    }

    public function reviewStore(Request $request, MarketplaceOrder $order)
    {
        $data=$request->validate(['rating'=>['required','integer','min:1','max:5'],'comment'=>['nullable','string','max:2000']]);
        if((int)$order->buyer_id !== (int)$request->user()->id || $order->status !== 'paid') abort(403);
        if(\Semizzy\Addons\Marketplace\Models\MarketplaceReview::query()->where('order_id',$order->id)->where('buyer_id',$request->user()->id)->exists()) return response()->json(['success'=>false,'message'=>'This order has already been reviewed.'],422);
        $review=\Semizzy\Addons\Marketplace\Models\MarketplaceReview::create(['product_id'=>$order->product_id,'buyer_id'=>$request->user()->id,'order_id'=>$order->id,'rating'=>$data['rating'],'comment'=>$data['comment']??null]);
        return response()->json(['success'=>true,'review'=>$review],201);
    }

    public function productStore(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:180'],
            'description' => ['nullable','string','max:10000'],
            'category' => ['required','string','max:100'],
            'product_type' => ['required', Rule::in(['physical','digital','service'])],
            'condition' => ['nullable', Rule::in(['new','used','refurbished','open_box','like_new','pre_owned','for_parts'])],
            'delivery_type' => ['nullable', Rule::in(['seller_fulfilled','download','license_key','service_delivery'])],
            'requires_shipping' => ['nullable','boolean'],
            'download_limit' => ['nullable','integer','min:1','max:1000000'],
            'service_delivery_days' => ['nullable','integer','min:1','max:3650'],
            'service_model' => ['nullable', Rule::in(['fixed','hourly','custom','milestone'])],
            'price_minor' => ['required','regex:/^[1-9]\d*$/','max:30'],
            'stock_quantity' => ['required','regex:/^\d+$/','max:30'],
            'currency' => ['required','string','size:3'],
        ]);
        if ($data['product_type'] === 'physical' && empty($data['condition'])) {
            $data['condition'] = 'new';
        }
        if ($data['product_type'] !== 'physical') {
            $data['condition'] = null;
        }
        $data['delivery_type'] = $data['delivery_type'] ?? match ($data['product_type']) {
            'digital' => 'download',
            'service' => 'service_delivery',
            default => 'seller_fulfilled',
        };
        $data['requires_shipping'] = $data['product_type'] === 'physical' && ($data['requires_shipping'] ?? true);
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
            'product_type'=>['required',Rule::in(['physical','digital','service'])],
            'condition'=>['nullable',Rule::in(['new','used','refurbished','open_box','like_new','pre_owned','for_parts'])],
            'delivery_type'=>['nullable',Rule::in(['seller_fulfilled','download','license_key','service_delivery'])],
            'requires_shipping'=>['nullable','boolean'],
            'download_limit'=>['nullable','integer','min:1','max:1000000'],
            'service_delivery_days'=>['nullable','integer','min:1','max:3650'],
            'service_model'=>['nullable',Rule::in(['fixed','hourly','custom','milestone'])],
            'price_minor'=>['required','regex:/^[1-9]\d*$/','max:30'],
            'stock_quantity'=>['required','regex:/^\d+$/','max:30'],
            'currency'=>['required','string','size:3'],
            'status'=>['required',Rule::in(['draft','active','paused','archived'])],
        ]);
        $data['currency']=strtoupper($data['currency']);
        if ($data['product_type'] === 'physical' && empty($data['condition'])) { $data['condition'] = 'new'; }
        if ($data['product_type'] !== 'physical') { $data['condition'] = null; }
        $data['delivery_type'] = $data['delivery_type'] ?? match ($data['product_type']) { 'digital' => 'download', 'service' => 'service_delivery', default => 'seller_fulfilled' };
        $data['requires_shipping'] = $data['product_type'] === 'physical' && ($data['requires_shipping'] ?? true);
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
