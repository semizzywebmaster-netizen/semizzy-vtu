<?php

namespace Semizzy\Addons\Marketplace\Http\Controllers;

use Illuminate\Http\Request;
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
}
