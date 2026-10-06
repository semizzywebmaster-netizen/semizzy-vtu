<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CacOrder;
use App\Models\CacServiceProduct;
use App\Services\Cac\CacOrderService;
use Illuminate\Http\Request;

class CacOrderController extends Controller
{
    public function products()
    {
        return response()->json([
            'data' => CacServiceProduct::query()->where('enabled', true)->orderBy('name')->get([
                'id','identifier','name','service_type','description','currency','selling_price_minor','requirements'
            ]),
        ]);
    }

    public function store(Request $request, CacOrderService $orders)
    {
        $data = $request->validate([
            'service_product_id' => ['required','integer','exists:cac_service_products,id'],
            'customer_name' => ['nullable','string','max:150'],
            'business_name' => ['nullable','string','max:255'],
            'company_type' => ['nullable','string','max:100'],
            'idempotency_key' => ['nullable','string','max:100'],
            'payload' => ['nullable','array'],
        ]);

        $product = CacServiceProduct::query()->findOrFail($data['service_product_id']);
        $payload = array_merge((array) ($data['payload'] ?? []), array_filter([
            'customer_name' => $data['customer_name'] ?? null,
            'business_name' => $data['business_name'] ?? null,
            'company_type' => $data['company_type'] ?? null,
        ], fn ($v) => $v !== null));

        $order = $orders->create((int) $request->user()->id, $product, $payload, $data['idempotency_key'] ?? null);

        return response()->json(['data' => $order->fresh('product')], 201);
    }

    public function show(Request $request, CacOrder $order)
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);

        return response()->json(['data' => $order->load(['product','documents','attempts'])]);
    }
}
