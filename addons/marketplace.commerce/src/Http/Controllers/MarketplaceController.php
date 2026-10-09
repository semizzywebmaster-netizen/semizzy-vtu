<?php

namespace Semizzy\Addons\Marketplace\Http\Controllers;

use App\Models\WalletMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use RuntimeException;
use Semizzy\Addons\Marketplace\Models\MarketplaceCategory;
use Semizzy\Addons\Marketplace\Models\MarketplaceEarning;
use Semizzy\Addons\Marketplace\Models\MarketplaceDigitalAsset;
use Semizzy\Addons\Marketplace\Models\MarketplaceDigitalDelivery;
use Semizzy\Addons\Marketplace\Models\MarketplaceServiceMilestone;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
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
                ->with(['category','media'])
                ->where('status', 'active')
                ->latest('published_at')
                ->latest()
                ->paginate(24),
            'categories' => $categories,
        ]);
    }


    public function sell()
    {
        return Inertia::render('Marketplace/Sell');
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

    public function updateCategoryProfit(Request $request, MarketplaceCategory $category)
    {
        $data = $request->validate([
            'sale_profit_percent' => ['required','numeric','min:0','max:100'],
            'sale_profit_fixed_minor' => ['nullable','string','max:30'],
        ]);
        $percent = (float) $data['sale_profit_percent'];
        if ($percent < 0 || $percent > 100) {
            return response()->json(['success' => false, 'message' => 'Profit percentage must be between 0 and 100.'], 422);
        }
        $fixed = (string) ($data['sale_profit_fixed_minor'] ?? '0');
        if (!preg_match('/^\d+$/', $fixed)) {
            return response()->json(['success' => false, 'message' => 'Fixed profit must be a whole amount in minor currency units.'], 422);
        }
        $category->forceFill([
            'sale_profit_bps' => (int) round($percent * 100),
            'sale_profit_fixed_minor' => $fixed,
        ])->save();

        return response()->json(['success' => true, 'category' => $category->fresh(), 'message' => 'Category sales profit settings updated.']);
    }

    public function admin()
    {
        $reconciliationOrders = MarketplaceOrder::query()->latest()->limit(100)->get();
        $references = $reconciliationOrders->pluck('reference')->filter()->values();
        $movementKeys = [];
        foreach ($references as $reference) {
            foreach (['buyer', 'seller', 'refund:buyer', 'refund:seller'] as $suffix) {
                $movementKeys[] = 'marketplace:'.$reference.':'.$suffix;
            }
        }
        $movements = WalletMovement::query()->whereIn('operation_key', $movementKeys)->get()->keyBy('operation_key');
        $earnings = MarketplaceEarning::query()->whereIn('order_id', $reconciliationOrders->pluck('id'))->get()->keyBy('order_id');
        $escrowRows = DB::table('marketplace_escrows')->whereIn('order_id', $reconciliationOrders->pluck('id'))->get()->keyBy('order_id');
        $reconciliation = [];
        foreach ($reconciliationOrders as $order) {
            $earning = $earnings->get($order->id);
            $escrow = $escrowRows->get($order->id);
            $buyerKey = 'marketplace:'.$order->reference.':buyer';
            $sellerKey = 'marketplace:'.$order->reference.':seller';
            $refundBuyerKey = 'marketplace:'.$order->reference.':refund:buyer';
            $refundSellerKey = 'marketplace:'.$order->reference.':refund:seller';
            $hasBuyerMovement = $movements->has($buyerKey);
            $hasSellerMovement = $movements->has($sellerKey);
            $hasRefundBuyer = $movements->has($refundBuyerKey);
            $hasRefundSeller = $movements->has($refundSellerKey);
            $issues = [];
            if ($order->status === 'pending' && ($hasBuyerMovement || $hasSellerMovement || $earning)) {
                $issues[] = 'Pending order has settlement records';
            }
            if ($order->status === 'paid') {
                if (!$hasBuyerMovement) $issues[] = 'Buyer debit movement missing';
                if (!$escrow) $issues[] = 'Escrow record missing';
                elseif (in_array($escrow->status, ['held', 'buyer_confirmed'], true) && $hasSellerMovement) $issues[] = 'Seller appears paid while escrow is still held';
                elseif ($escrow->status === 'released' && !$hasSellerMovement) $issues[] = 'Released escrow is missing seller payout movement';
                elseif (!in_array($escrow->status, ['held', 'buyer_confirmed', 'released'], true)) $issues[] = 'Unexpected escrow state for paid order';
                if (!$earning) $issues[] = 'Earning record missing';
                elseif ($escrow && in_array($escrow->status, ['held', 'buyer_confirmed'], true) && $earning->status !== 'escrowed') $issues[] = 'Earning status should remain escrowed';
                elseif ($escrow && $escrow->status === 'released' && $earning->status !== 'credited') $issues[] = 'Released escrow earning status mismatch';
                if ($hasRefundBuyer || $hasRefundSeller) $issues[] = 'Refund movement exists while order is paid';
            }
            if ($order->status === 'refunded') {
                if (!$hasRefundBuyer) $issues[] = 'Buyer refund movement missing';
                if (!$hasRefundSeller) $issues[] = 'Seller reversal movement missing';
                if (!$earning) $issues[] = 'Earning record missing';
                elseif ($earning->status !== 'refunded') $issues[] = 'Earning status does not match refunded order';
            }
            if ($order->status === 'cancelled' && ($hasBuyerMovement || $hasSellerMovement || $earning)) {
                $issues[] = 'Cancelled order has settlement records';
            }
            if ($issues) {
                $reconciliation[] = [
                    'order_id' => $order->id,
                    'reference' => $order->reference,
                    'status' => $order->status,
                    'currency' => $order->currency,
                    'gross_minor' => (string) $order->total_minor,
                    'earning_status' => $earning?->status,
                    'issues' => $issues,
                ];
            }
        }

        return Inertia::render('Admin/Marketplace/Index', [
            'products' => MarketplaceProduct::query()->with(['seller','category'])->latest()->paginate(30),
            'orders' => MarketplaceOrder::query()->with(['buyer', 'seller', 'product'])->latest()->paginate(30),
            'earnings' => MarketplaceEarning::query()->with(['seller', 'category', 'order'])->latest()->paginate(30),
                        'disputes' => DB::table('marketplace_disputes as d')
                ->join('marketplace_orders as o', 'o.id', '=', 'd.order_id')
                ->join('users as b', 'b.id', '=', 'o.buyer_id')
                ->join('users as s', 's.id', '=', 'o.seller_id')
                ->select('d.id','d.order_id','d.reason','d.description','d.status as dispute_status','d.resolution','d.resolution_note','d.created_at','o.reference','o.currency','o.total_minor','b.name as buyer_name','s.name as seller_name')
                ->orderByRaw("CASE WHEN d.status IN ('open','under_review','awaiting_evidence') THEN 0 ELSE 1 END")
                ->orderByDesc('d.created_at')->limit(100)->get(),
'escrows' => DB::table('marketplace_escrows as e')
                ->join('marketplace_orders as o', 'o.id', '=', 'e.order_id')
                ->join('users as b', 'b.id', '=', 'o.buyer_id')
                ->join('users as s', 's.id', '=', 'o.seller_id')
                ->select('e.id','e.order_id','e.reference','e.currency','e.gross_minor','e.seller_net_minor','e.platform_profit_minor','e.status as escrow_status','e.buyer_confirmed_at','e.released_at','e.admin_note','b.name as buyer_name','s.name as seller_name')
                ->orderByRaw("CASE WHEN e.status IN ('held','buyer_confirmed') THEN 0 ELSE 1 END")
                ->orderByDesc('e.created_at')->limit(100)->get(),
            'categories' => MarketplaceCategory::query()->with('parent')->orderBy('sort_order')->orderBy('name')->get(),
            'reconciliation' => [
                'checked_orders' => $reconciliationOrders->count(),
                'issue_count' => count($reconciliation),
                'items' => $reconciliation,
            ],
        ]);
    }

    public function myOrders(Request $request)
    {
        $orders = MarketplaceOrder::query()->with(['product','seller'])
            ->where('buyer_id', (int) $request->user()->id)
            ->latest()->paginate(20);
        $disputes = DB::table('marketplace_disputes')->whereIn('order_id', $orders->getCollection()->pluck('id'))->orderByDesc('created_at')->get()->groupBy('order_id');
        $escrows = DB::table('marketplace_escrows')->whereIn('order_id', $orders->getCollection()->pluck('id'))
            ->get(['order_id','status as escrow_status','buyer_confirmed_at','released_at'])
            ->keyBy('order_id');
        $orders->getCollection()->transform(function ($order) use ($escrows) {
            $order->escrow = $escrows->get($order->id);
            $order->disputes = $disputes->get($order->id, collect())->values();
            return $order;
        });
        return Inertia::render('Marketplace/MyOrders', ['orders' => $orders]);
    }

    public function updateShipping(Request $request, MarketplaceOrder $order)
    {
        if ((int) $order->seller_id !== (int) $request->user()->id) abort(403);
        $data = $request->validate([
            'shipping_carrier' => ['required', 'string', 'max:120'],
            'tracking_number' => ['required', 'string', 'max:180'],
            'tracking_url' => ['nullable', 'url', 'starts_with:https://', 'max:2000'],
        ]);
        if ($order->status !== 'paid') abort(422, 'Only paid orders can be shipped.');
        $order->load('product');
        if (!$order->product?->isPhysical()) abort(422, 'Tracking details are only available for physical products.');
        $order->forceFill($data + ['fulfillment_status' => 'shipped', 'delivery_status' => 'shipped', 'shipped_at' => now()])->save();
        return response()->json(['success' => true, 'message' => 'Shipping details saved.', 'order' => $order->fresh()]);
    }

    public function openDispute(Request $request, MarketplaceOrder $order)
    {
        if ((int) $order->buyer_id !== (int) $request->user()->id) abort(403);
        if ($order->status !== 'paid') abort(422, 'Only paid orders can be disputed.');
        $data = $request->validate([
            'reason' => ['required', 'string', Rule::in(['item_not_received','item_damaged','item_not_as_described','digital_delivery_missing','service_not_completed','other'])],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
        ]);
        $existing = DB::table('marketplace_disputes')->where('order_id', $order->id)->whereIn('status', ['open','under_review','awaiting_evidence'])->exists();
        if ($existing) return response()->json(['success' => false, 'message' => 'An active dispute already exists for this order.'], 422);
        $id = DB::table('marketplace_disputes')->insertGetId([
            'order_id' => $order->id, 'opened_by' => $request->user()->id, 'reason' => $data['reason'],
            'description' => $data['description'], 'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['success' => true, 'message' => 'Dispute submitted. Escrow will remain held while admin reviews it.', 'dispute_id' => $id], 201);
    }

    public function resolveDispute(Request $request, int $dispute)
    {
        $data = $request->validate([
            'resolution' => ['required', 'string', Rule::in(['refund_buyer','release_seller','awaiting_evidence','dismiss'])],
            'resolution_note' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        try {
            DB::transaction(function () use ($request, $dispute, $data): void {
                $case = DB::table('marketplace_disputes')->where('id', $dispute)->lockForUpdate()->first();
                if (!$case || !in_array($case->status, ['open','under_review','awaiting_evidence'], true)) {
                    throw new RuntimeException('This dispute is not open for resolution.');
                }
                if ($data['resolution'] === 'awaiting_evidence') {
                    DB::table('marketplace_disputes')->where('id', $dispute)->update([
                        'status' => 'awaiting_evidence', 'resolution_note' => $data['resolution_note'],
                        'resolved_by' => $request->user()->id, 'updated_at' => now(),
                    ]);
                    return;
                }
                if ($data['resolution'] === 'dismiss') {
                    DB::table('marketplace_disputes')->where('id', $dispute)->update([
                        'status' => 'dismissed', 'resolution' => 'dismiss', 'resolution_note' => $data['resolution_note'],
                        'resolved_by' => $request->user()->id, 'resolved_at' => now(), 'updated_at' => now(),
                    ]);
                    return;
                }
                DB::table('marketplace_disputes')->where('id', $dispute)->update([
                    'status' => 'resolved', 'resolution' => $data['resolution'], 'resolution_note' => $data['resolution_note'],
                    'resolved_by' => $request->user()->id, 'resolved_at' => now(), 'updated_at' => now(),
                ]);
                $order = MarketplaceOrder::query()->findOrFail($case->order_id);
                $service = app(MarketplaceOrderService::class);
                if ($data['resolution'] === 'refund_buyer') {
                    $service->refund($order, (int) $request->user()->id, true);
                } else {
                    $service->releaseEscrow($order, (int) $request->user()->id, 'Dispute #'.$dispute.' resolution: '.$data['resolution_note']);
                }
            });
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'message' => 'Dispute decision recorded and any authorised financial action processed.']);
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

    public function confirmReceipt(Request $request, MarketplaceOrder $order, MarketplaceOrderService $orders)
    {
        try {
            $order = $orders->confirmReceipt($order, (int) $request->user()->id);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'message' => 'Receipt confirmed. Escrow remains held until an admin authorises release.', 'order' => $order]);
    }

    public function releaseEscrow(Request $request, MarketplaceOrder $order, MarketplaceOrderService $orders)
    {
        $data = $request->validate(['admin_note' => ['required', 'string', 'min:5', 'max:1000']]);
        try {
            $order = $orders->releaseEscrow($order, (int) $request->user()->id, $data['admin_note'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'message' => 'Escrow released to seller.', 'order' => $order]);
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
        $videoUrl = $data['video_url'] ?? null;
        $product = MarketplaceProduct::create($this->normaliseProductData($data, (int) $request->user()->id));
        $this->syncExternalVideo($product, $videoUrl);

        return response()->json(['success'=>true,'product'=>$product->fresh(['category','media'])],201);
    }

    public function productUpdate(Request $request, MarketplaceProduct $product)
    {
        if ((int)$product->seller_id !== (int)$request->user()->id && !$request->user()->hasPermission('marketplace.manage')) abort(403);

        try {
            $data = $this->validatedProductData($request, $product);
        } catch (RuntimeException $e) {
            return response()->json(['success'=>false,'message'=>$e->getMessage()],422);
        }
        $videoUrl = array_key_exists('video_url', $data) ? $data['video_url'] : null;
        $hasVideoInput = array_key_exists('video_url', $data);
        $product->forceFill($this->normaliseProductData($data, (int) $product->seller_id, $product))->save();
        if ($hasVideoInput) $this->syncExternalVideo($product, $videoUrl);

        return response()->json(['success'=>true,'product'=>$product->fresh(['category','media'])]);
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
        if (!empty($data['path']) && !str_starts_with($data['path'], 'marketplace/digital-assets/'.$product->id.'/')) return response()->json(['success'=>false,'message'=>'Digital assets must use the protected marketplace asset directory.'],422);
        $asset = MarketplaceDigitalAsset::create(array_merge($data, ['product_id'=>$product->id,'active'=>true]));
        return response()->json(['success'=>true,'asset'=>$asset],201);
    }

    public function storeVideo(Request $request, MarketplaceProduct $product)
    {
        if ((int) $product->seller_id !== (int) $request->user()->id && !$request->user()->hasPermission('marketplace.manage')) abort(403);

        $data = $request->validate([
            'url' => ['required','url','max:2048'],
            'alt_text' => ['nullable','string','max:255'],
        ]);

        try {
            $media = $this->syncExternalVideo($product, $data['url'], $data['alt_text'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'media' => $media], 201);
    }

    public function storeMedia(Request $request, MarketplaceProduct $product)
    {
        if ((int) $product->seller_id !== (int) $request->user()->id && !$request->user()->hasPermission('marketplace.manage')) abort(403);

        $data = $request->validate([
            'media' => ['required','file','mimes:jpg,jpeg,png,webp,gif','max:10240'],
            'alt_text' => ['nullable','string','max:255'],
            'is_primary' => ['nullable','boolean'],
        ]);

        $file = $data['media'];
        $path = $file->store('marketplace/products/'.$product->id, 'public');
        $media = \Semizzy\Addons\Marketplace\Models\MarketplaceProductMedia::create([
            'product_id' => $product->id,
            'media_type' => 'image',
            'url' => Storage::disk('public')->url($path),
            'disk' => 'public',
            'path' => $path,
            'alt_text' => $data['alt_text'] ?? null,
            'sort_order' => ((int) $product->media()->max('sort_order')) + 1,
            'is_primary' => (bool) ($data['is_primary'] ?? false),
        ]);

        if ($media->is_primary) {
            $product->media()->where('id', '!=', $media->id)->update(['is_primary' => false]);
        }

        if (!$product->media()->where('is_primary', true)->exists()) {
            $media->forceFill(['is_primary' => true])->save();
        }

        return response()->json(['success' => true, 'media' => $media], 201);
    }

    public function setPrimaryMedia(Request $request, MarketplaceProduct $product, int $media)
    {
        if ((int) $product->seller_id !== (int) $request->user()->id && !$request->user()->hasPermission('marketplace.manage')) abort(403);
        $item = $product->media()->whereKey($media)->firstOrFail();
        $product->media()->update(['is_primary' => false]);
        $item->forceFill(['is_primary' => true])->save();
        return response()->json(['success' => true, 'media' => $item]);
    }

    public function deleteMedia(Request $request, MarketplaceProduct $product, int $media)
    {
        if ((int) $product->seller_id !== (int) $request->user()->id && !$request->user()->hasPermission('marketplace.manage')) abort(403);
        $item = $product->media()->whereKey($media)->firstOrFail();
        if ($item->disk && $item->path) {
            Storage::disk($item->disk)->delete($item->path);
        }
        $wasPrimary = (bool) $item->is_primary;
        $item->delete();

        if ($wasPrimary) {
            $replacement = $product->media()->orderBy('sort_order')->first();
            if ($replacement) $replacement->forceFill(['is_primary' => true])->save();
        }

        return response()->json(['success' => true]);
    }

    public function storeDigitalAssetFile(Request $request, MarketplaceProduct $product)
    {
        if ((int) $product->seller_id !== (int) $request->user()->id && !$request->user()->hasPermission('marketplace.manage')) abort(403);
        if (!$product->isDigital()) abort(422, 'Only digital products can have digital assets.');

        $data = $request->validate([
            'asset' => ['required','file','max:51200'],
            'asset_type' => ['required', Rule::in(['download','license_key','course','media'])],
            'version' => ['nullable','string','max:80'],
            'checksum' => ['nullable','string','max:255'],
        ]);

        $path = $data['asset']->store('marketplace/digital-assets/'.$product->id, 'local');
        $asset = MarketplaceDigitalAsset::create([
            'product_id' => $product->id,
            'asset_type' => $data['asset_type'],
            'disk' => 'local',
            'path' => $path,
            'external_url' => null,
            'version' => $data['version'] ?? null,
            'checksum' => $data['checksum'] ?? null,
            'sort_order' => ((int) $product->digitalAssets()->max('sort_order')) + 1,
            'active' => true,
        ]);

        return response()->json(['success' => true, 'asset' => $asset], 201);
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
            'video_url'=>['nullable','url','max:2048'],
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
        if ($data['product_type'] === 'physical' && in_array($data['condition'] ?? 'new', ['used','refurbished','open_box','like_new','pre_owned','for_parts'], true) && empty($data['attributes']['condition_notes'])) {
            throw new RuntimeException('Condition notes are required for non-new physical listings.');
        }

        if (!empty($data['video_url'])) $this->validateExternalVideoUrl($data['video_url']);

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
        unset($data['category_name'], $data['video_url']);

        if (!$existing) {
            $data['slug'] = Str::slug($data['name']).'-'.strtolower(Str::random(8));
        }
        if (($data['status'] ?? null) === 'active') {
            $data['published_at'] = $existing?->published_at ?? now();
        }

        return $data;
    }

    private function syncExternalVideo(MarketplaceProduct $product, ?string $url, ?string $altText = null): ?\Semizzy\Addons\Marketplace\Models\MarketplaceProductMedia
    {
        if ($url) $this->validateExternalVideoUrl($url);
        $product->media()->where('media_type', 'video')->delete();
        if (!$url) return null;
        return \Semizzy\Addons\Marketplace\Models\MarketplaceProductMedia::create([
            'product_id' => $product->id,
            'media_type' => 'video',
            'url' => $url,
            'disk' => null,
            'path' => null,
            'alt_text' => $altText,
            'sort_order' => ((int) $product->media()->max('sort_order')) + 1,
            'is_primary' => false,
        ]);
    }

    private function validateExternalVideoUrl(string $url): void
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https') {
            throw new RuntimeException('Video links must use HTTPS.');
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = strtolower((string) ($parts['path'] ?? ''));
        $allowedHosts = [
            'youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be',
            'youtube-nocookie.com', 'www.youtube-nocookie.com',
            'vimeo.com', 'www.vimeo.com', 'player.vimeo.com',
        ];
        $isDirectVideo = (bool) preg_match('/\\.(mp4|webm|ogg)(?:$|\\?)/i', $path);
        if (!in_array($host, $allowedHosts, true) && !$isDirectVideo) {
            throw new RuntimeException('Use a YouTube, Vimeo, or direct HTTPS video URL.');
        }

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
