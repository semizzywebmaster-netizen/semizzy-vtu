<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProviderServiceProduct;
use App\Models\ApiProvider;
use App\Models\ProviderServiceMapping;
use App\Services\Catalogue\ProviderCatalogueSyncService;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CatalogueController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Catalogue', [
            'categories' => ServiceCategory::query()->with(['services.products'])->orderBy('sort_order')->get(),
        ]);
    }

    public function storeCategory(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data=$request->validate([
            'key'=>'required|string|max:100|alpha_dash|unique:service_categories,key',
            'name'=>'required|string|max:160',
            'description'=>'nullable|string|max:5000',
            'sort_order'=>'nullable|integer|min:0|max:100000',
            'enabled'=>'nullable|boolean',
        ]);
        $category = ServiceCategory::create($data+['enabled'=>$data['enabled']??true]);
        $audit->record('catalogue.category.created', $category, ['key' => $category->key], $request);
        return back()->with('success','Service category created.');
    }

    public function storeService(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data=$request->validate([
            'category_id'=>'required|integer|exists:service_categories,id',
            'key'=>'required|string|max:100|alpha_dash|unique:services,key',
            'name'=>'required|string|max:160',
            'description'=>'nullable|string|max:5000',
            'metadata'=>'nullable|array',
            'enabled'=>'nullable|boolean',
        ]);
        $service = Service::create($data+['enabled'=>$data['enabled']??true]);
        $audit->record('catalogue.service.created', $service, ['key' => $service->key], $request);
        return back()->with('success','Service created.');
    }

    public function storeProduct(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data=$request->validate([
            'service_id'=>'required|integer|exists:services,id',
            'key'=>'required|string|max:120',
            'name'=>'required|string|max:200',
            'currency'=>'required|string|size:3|regex:/^[A-Za-z]{3}$/',
            'metadata'=>'nullable|array',
            'enabled'=>'nullable|boolean',
        ]);
        if(ServiceProduct::query()->where('service_id',$data['service_id'])->where('key',$data['key'])->exists())
            return back()->with('error','A product with this key already exists under this service.');
        $data['currency'] = strtoupper($data['currency']);
        $product = ServiceProduct::create($data+['enabled'=>$data['enabled']??false]);
        $audit->record('catalogue.product.created', $product, ['service_id' => $product->service_id, 'key' => $product->key], $request);
        return back()->with('success','Service product created.');
    }

    public function syncProvider(Request $request, ProviderCatalogueSyncService $sync): RedirectResponse
    {
        $data=$request->validate([
            'api_provider_id'=>'required|integer|exists:api_providers,id',
            'service_id'=>'required|integer|exists:services,id',
        ]);
        $provider=ApiProvider::findOrFail($data['api_provider_id']);
        $service=Service::findOrFail($data['service_id']);
        try {
            $count=$sync->sync($provider,$service);
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error','Catalogue sync failed safely. Review the server-side diagnostics.');
        }
        return back()->with('success',"Catalogue sync completed. {$count} product record(s) processed.");
    }

    public function syncAllVerified(Request $request, ProviderCatalogueSyncService $sync, AuditLogger $audit): RedirectResponse
    {
        $providers = ApiProvider::query()
            ->where('enabled', true)
            ->where('paused', false)
            ->whereIn('verification_status', ['sandbox_verified', 'live_verified'])
            ->whereIn('integration_status', ['sandbox_verified', 'live_verified'])
            ->whereJsonContains('capabilities', 'catalogue_retrieval')
            ->orderBy('priority')
            ->get();

        $processed = 0;
        $products = 0;
        $failures = [];

        foreach ($providers as $provider) {
            $services = Service::query()
                ->where('enabled', true)
                ->whereJsonContains('metadata->catalogue_request', 'service', true)
                ->orderBy('id')
                ->get();

            foreach ($services as $service) {
                try {
                    $products += $sync->sync($provider, $service);
                    $processed++;
                } catch (\Throwable $e) {
                    report($e);
                    $failures[] = $provider->display_name.' / '.$service->name;
                }
            }
        }

        $audit->record('catalogue.sync_all_completed', null, [
            'providers_considered' => $providers->count(),
            'service_syncs_completed' => $processed,
            'products_processed' => $products,
            'failures' => $failures,
        ], $request);

        if ($providers->isEmpty()) {
            return back()->with('error', 'No enabled, verified provider with catalogue retrieval capability is ready for sync.');
        }

        if ($failures) {
            return back()->with('error', "Catalogue sync completed with {$processed} successful service sync(s), {$products} product record(s), and ".count($failures)." failure(s).");
        }

        return back()->with('success', "Catalogue sync completed successfully: {$processed} service sync(s), {$products} product record(s) processed.");
    }


    public function toggleMapping(ProviderServiceMapping $mapping, AuditLogger $audit, Request $request): RedirectResponse
    {
        return DB::transaction(function () use ($mapping, $audit, $request): RedirectResponse {
            $mapping = ProviderServiceMapping::query()->lockForUpdate()->findOrFail($mapping->id);
            $provider = ApiProvider::query()->lockForUpdate()->findOrFail($mapping->api_provider_id);

            if (!$mapping->enabled && (! $provider->enabled || $provider->paused || $provider->verification_status !== 'live_verified' || $provider->integration_status !== 'live_verified')) {
                return back()->with('error','A provider service mapping can only be enabled for an enabled, unpaused, live-verified provider.');
            }

            $enabled = ! $mapping->enabled;
            $mapping->update(['enabled' => $enabled]);
            $audit->record($enabled ? 'catalogue.mapping.enabled' : 'catalogue.mapping.disabled', $mapping, [
                'provider_id' => $mapping->api_provider_id,
                'service_id' => $mapping->service_id,
            ], $request);

            return back()->with('success','Provider service mapping status updated.');
        });
    }

    public function disableProduct(ServiceProduct $product, AuditLogger $audit, Request $request): RedirectResponse
    {
        DB::transaction(function () use ($product): void {
            $product = ServiceProduct::query()->lockForUpdate()->findOrFail($product->id);
            ProviderServiceProduct::query()->where('service_product_id', $product->id)->lockForUpdate()->get()->each->update(['enabled' => false]);
            $product->update(['enabled' => false]);
        });
        $audit->record('catalogue.product.disabled', $product, ['service_id' => $product->service_id], $request);
        return back()->with('success','Product and provider mappings disabled.');
    }
}
