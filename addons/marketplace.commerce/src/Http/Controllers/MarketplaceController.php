<?php

namespace Semizzy\Addons\Marketplace\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use RuntimeException;
use Semizzy\Addons\Marketplace\Models\MarketplaceCategory;
use Semizzy\Addons\Marketplace\Models\MarketplaceOrder;
use Semizzy\Addons\Marketplace\Models\MarketplaceProduct;
use Semizzy\Addons\Marketplace\Services\MarketplaceOrderService;

final class MarketplaceController
{
    public function index()
    {
        $categories = MarketplaceCategory::query()
            ->where('active', true)
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->where('active', true)])
            ->orderBy('sort_order')->orderBy('name')->get();

        return Inertia::render('Marketplace/Index', [
            'products' => MarketplaceProduct::query()
                ->with('category')
                ->where('status', 'active')
                ->latest('published_at')
                ->latest()
                ->paginate(24),
            'categories' => $categories,
        ]);
    }

    public function categories()
    {
        $categories = MarketplaceCategory::query()
            ->where('active', true)
            ->with(['children' => fn ($q) => $q->where('active', true)])
            ->whereNull('parent_id')
            ->orderBy('sort_order')->orderBy('name')->get();

        return response()->json(['success' => true, 'categories' => $categories]);
    }

    public function admin()
    {
        return Inertia::render('Admin/Marketplace/Index', [
            'products' => MarketplaceProduct::query()->with(['seller','category'])->latest()->paginate(30),
            'orders' => MarketplaceOrder::query()->with(['buyer', 'seller', 'product'])->latest()->paginate(30),
            'categories' => MarketplaceCategory::query()->with('parent')->orderBy('sort_order')->orderBy('name')->get(),
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
        $data = $this->validatedProductData($request);
        $product = MarketplaceProduct::create($this->normaliseProductData($data, (int) $request->user()->id));

        return response()->json(['success'=>true,'product'=>$product->fresh('category')],201);
    }

    public function productUpdate(Request $request, MarketplaceProduct $product)
    {
        if ((int)$product->seller_id !== (int)$request->user()->id && !$request->user()->hasPermission('marketplace.manage')) abort(403);

        $data = $this->validatedProductData($request, $product);
        $product->forceFill($this->normaliseProductData($data, (int) $product->seller_id, $product))->save();

        return response()->json(['success'=>true,'product'=>$product->fresh('category')]);
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

    private function validatedProductData(Request $request, ?MarketplaceProduct $existing = null): array
    {
        $data = $request->validate([
            'name'=>['required','string','max:180'],
            'description'=>['nullable','string','max:10000'],
            'category_id'=>['required','integer','exists:marketplace_categories,id'],
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
            'attributes'=>['nullable','array'],
            'status'=>['sometimes','required',Rule::in(['draft','active','paused','archived'])],
        ]);

        $category = MarketplaceCategory::query()->where('active', true)->findOrFail((int) $data['category_id']);
        if ((string)$data['product_type'] !== (string)$category->product_type) {
            throw new RuntimeException('The selected category requires product type: '.$category->product_type.'.');
        }

        $schema = $this->categorySchema($category);
        $attributes = is_array($data['attributes'] ?? null) ? $data['attributes'] : [];
        $this->validateCategoryAttributes($attributes, $schema);

        $data['category_name'] = $category->name;
        $data['listing_type'] = $category->listing_type;
        $data['attributes'] = $attributes;
        $data['category_model'] = $category;
        $data['status'] = $data['status'] ?? ($existing?->status ?? 'active');

        return $data;
    }

    private function normaliseProductData(array $data, int $sellerId, ?MarketplaceProduct $existing = null): array
    {
        /** @var MarketplaceCategory $category */
        $category = $data['category_model'];
        unset($data['category_model']);

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
        $data['category'] = $data['category_name'];
        $data['category_id'] = $category->id;
        $data['seller_id'] = $sellerId;
        unset($data['category_name']);

        if (!$existing) {
            $data['slug'] = Str::slug($data['name']).'-'.strtolower(Str::random(8));
        }
        if (($data['status'] ?? null) === 'active') {
            $data['published_at'] = $existing?->published_at ?? now();
        }

        return $data;
    }

    private function categorySchema(MarketplaceCategory $category): array
    {
        $schema = $category->attribute_schema ?? [];
        $parents = [];
        $parent = $category->parent;
        while ($parent) {
            $parents[] = $parent;
            $parent = $parent->parent;
        }
        foreach (array_reverse($parents) as $ancestor) {
            $schema = array_merge($ancestor->attribute_schema ?? [], $schema);
        }
        return $schema;
    }

    private function validateCategoryAttributes(array $attributes, array $schema): void
    {
        foreach ($schema as $key => $definition) {
            if (($definition['required'] ?? false) && (!array_key_exists($key, $attributes) || $attributes[$key] === null || $attributes[$key] === '')) {
                throw new RuntimeException('Missing required category field: '.$key.'.');
            }
        }

        foreach ($attributes as $key => $value) {
            if (!array_key_exists($key, $schema)) {
                throw new RuntimeException('Unsupported category field: '.$key.'.');
            }
            if ($value === null || $value === '') {
                continue;
            }
            $definition = $schema[$key];
            $type = $definition['type'] ?? 'string';

            if ($type === 'select' && isset($definition['options']) && !in_array($value, $definition['options'], true)) {
                throw new RuntimeException('Invalid value for category field: '.$key.'.');
            }
            if ($type === 'integer' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                throw new RuntimeException('Category field '.$key.' must be an integer.');
            }
            if ($type === 'boolean' && !is_bool($value) && !in_array($value, [0,1,'0','1'], true)) {
                throw new RuntimeException('Category field '.$key.' must be boolean.');
            }
            if ($type === 'date' && !strtotime((string)$value)) {
                throw new RuntimeException('Category field '.$key.' must be a valid date.');
            }
            if (isset($definition['min']) && is_numeric($value) && (int)$value < (int)$definition['min']) {
                throw new RuntimeException('Category field '.$key.' is below the minimum allowed value.');
            }
            if (isset($definition['max']) && is_numeric($value) && (int)$value > (int)$definition['max']) {
                throw new RuntimeException('Category field '.$key.' exceeds the maximum allowed value.');
            }
        }
    }
}
