<?php

namespace Semizzy\Addons\Marketplace\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use RuntimeException;
use Semizzy\Addons\Marketplace\Models\MarketplaceCategory;
use Semizzy\Addons\Marketplace\Models\MarketplaceDigitalAsset;
use Semizzy\Addons\Marketplace\Models\MarketplaceDigitalDelivery;
use Semizzy\Addons\Marketplace\Models\MarketplaceServiceMilestone;
use Illuminate\Support\Facades\Storage;
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


    public function categoryForm(Request $request, int $category)
    {
        $item = MarketplaceCategory::query()->with('parent')->where('active', true)->findOrFail($category);
        $schema = $this->categorySchema($item);
        return response()->json([
            'success'=>true,
            'category'=>[
                'id'=>$item->id,'name'=>$item->name,'slug'=>$item->slug,
                'description'=>$item->description,'icon'=>$item->icon,'icon_type'=>$item->icon_type,
                'parent_id'=>$item->parent_id,'product_type'=>$item->product_type,
                'listing_type'=>$item->listing_type,'attribute_schema'=>$schema,
            ],
        ]);
    }

    public function sellerCategories(Request $request)
    {
        return response()->json([
            'success'=>true,
            'categories'=>MarketplaceCategory::query()
                ->where('active',true)
                ->orderByRaw('COALESCE(parent_id, id)')
                ->orderBy('parent_id')->orderBy('sort_order')->orderBy('name')->get([
                    'id','parent_id','name','slug','description','icon','icon_type','product_type','listing_type','attribute_schema'
                ]),
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
        try {
            $data = $this->validatedProductData($request);
        } catch (RuntimeException $e) {
            return response()->json(['success'=>false,'message'=>$e->getMessage()],422);
        }
        $product = MarketplaceProduct::create($this->normaliseProductData($data, (int) $request->user()->id));

        return response()->json(['success'=>true,'product'=>$product->fresh('category')],201);
    }

    public function productUpdate(Request $request, MarketplaceProduct $product)
    {
        if ((int)$product->seller_id !== (int)$request->user()->id && !$request->user()->hasPermission('marketplace.manage')) abort(403);

        try {
            $data = $this->validatedProductData($request, $product);
        } catch (RuntimeException $e) {
            return response()->json(['success'=>false,'message'=>$e->getMessage()],422);
        }
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

    public function addDigitalAsset(Request $request, MarketplaceProduct $product)
    {
        if ((int)$product->seller_id !== (int)$request->user()->id && !$request->user()->hasPermission('marketplace.manage')) abort(403);
        if (!$product->isDigital()) abort(422, 'Only digital products can have digital assets.');
        $data = $request->validate([
            'asset_type'=>['required',Rule::in(['download','license_key','course','media'])],
            'disk'=>['nullable','string','max:100'],'path'=>['nullable','string','max:2048'],
            'external_url'=>['nullable','url','max:2048'],'version'=>['nullable','string','max:80'],
            'checksum'=>['nullable','string','max:255'],'sort_order'=>['nullable','integer','min:0'],
        ]);
        if (empty($data['path']) && empty($data['external_url'])) return response()->json(['success'=>false,'message'=>'A storage path or external URL is required.'],422);
        if (!empty($data['path']) && empty($data['disk'])) return response()->json(['success'=>false,'message'=>'A storage disk is required for stored assets.'],422);
        $asset = MarketplaceDigitalAsset::create(array_merge($data, ['product_id'=>$product->id,'active'=>true]));
        return response()->json(['success'=>true,'asset'=>$asset],201);
    }

    public function downloadDigitalAsset(Request $request, string $token)
    {
        $delivery = MarketplaceDigitalDelivery::query()->with(['order.product','asset'])->where('delivery_token',$token)->firstOrFail();
        if ((int)$delivery->order->buyer_id !== (int)$request->user()->id) abort(403);
        if ($delivery->revoked_at || ($delivery->expires_at && $delivery->expires_at->isPast())) abort(410, 'This digital delivery has expired or been revoked.');
        if ($delivery->download_limit !== null && $delivery->download_count >= $delivery->download_limit) abort(429, 'Download limit reached.');
        $asset = $delivery->asset;
        if (!$asset || !$asset->active) abort(404);
        if ($asset->external_url) { $delivery->increment('download_count'); $delivery->forceFill(['last_downloaded_at'=>now()])->save(); return redirect()->away($asset->external_url); }
        if (!$asset->disk || !$asset->path || !Storage::disk($asset->disk)->exists($asset->path)) abort(404);
        $delivery->increment('download_count'); $delivery->forceFill(['last_downloaded_at'=>now()])->save();
        return Storage::disk($asset->disk)->download($asset->path);
    }

    public function serviceSubmit(Request $request, MarketplaceOrder $order)
    {
        if ((int)$order->seller_id !== (int)$request->user()->id) abort(403);
        $order->load('product');
        if ($order->product?->product_type !== 'service' || $order->status !== 'paid') abort(422, 'This order is not an active service order.');
        $data=$request->validate(['submission'=>['required','string','max:20000']]);
        $order->forceFill(['seller_submission'=>$data['submission'],'fulfillment_status'=>'submitted','service_status'=>'submitted','fulfilled_at'=>now()])->save();
        return response()->json(['success'=>true,'order'=>$order->fresh(['product','buyer','seller'])]);
    }

    public function serviceRevision(Request $request, MarketplaceOrder $order)
    {
        if ((int)$order->buyer_id !== (int)$request->user()->id) abort(403);
        $order->load('product');
        if ($order->product?->product_type !== 'service' || !in_array($order->service_status,['submitted','in_review'],true)) abort(422, 'This service is not awaiting review.');
        $data=$request->validate(['requirements'=>['required','string','max:10000']]);
        $order->forceFill(['buyer_requirements'=>$data['requirements'],'revision_count'=>((int)$order->revision_count)+1,'fulfillment_status'=>'in_progress','service_status'=>'revision_requested'])->save();
        return response()->json(['success'=>true,'order'=>$order->fresh(['product','buyer','seller'])]);
    }

    public function serviceAccept(Request $request, MarketplaceOrder $order)
    {
        if ((int)$order->buyer_id !== (int)$request->user()->id) abort(403);
        $order->load('product');
        if ($order->product?->product_type !== 'service' || $order->status !== 'paid' || !in_array($order->service_status,['submitted','in_review'],true)) abort(422, 'This service is not awaiting acceptance.');
        $order->forceFill(['fulfillment_status'=>'completed','service_status'=>'completed','accepted_at'=>now(),'completed_at'=>now()])->save();
        return response()->json(['success'=>true,'order'=>$order->fresh(['product','buyer','seller'])]);
    }

    public function serviceMilestones(Request $request, MarketplaceOrder $order)
    {
        if ((int)$order->buyer_id !== (int)$request->user()->id && (int)$order->seller_id !== (int)$request->user()->id && !$request->user()->hasPermission('marketplace.orders.manage')) abort(403);
        $order->load('product');
        if ($order->product?->product_type !== 'service') abort(422, 'This order is not a service order.');
        $data=$request->validate(['milestones'=>['required','array','min:1','max:50'],'milestones.*.title'=>['required','string','max:180'],'milestones.*.description'=>['nullable','string','max:5000'],'milestones.*.due_at'=>['nullable','date']]);
        $order->serviceMilestones()->delete();
        foreach($data['milestones'] as $i=>$milestone) MarketplaceServiceMilestone::create(['order_id'=>$order->id,'sequence'=>$i+1,'title'=>$milestone['title'],'description'=>$milestone['description']??null,'due_at'=>$milestone['due_at']??null]);
        return response()->json(['success'=>true,'milestones'=>$order->serviceMilestones()->orderBy('sequence')->get()],201);
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
