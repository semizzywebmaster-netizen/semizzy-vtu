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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CatalogueController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120']]);
        return Inertia::render('Admin/Catalogue', [
            'categories' => ServiceCategory::query()->with(['services.products'])->orderBy('sort_order')->get(),
            'search' => (string) ($filters['search'] ?? ''),
        ]);
    }

    public function storeCategory(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data=$request->validate(['key'=>'required|string|max:100|alpha_dash|unique:service_categories,key','name'=>'required|string|max:160','description'=>'nullable|string|max:5000','sort_order'=>'nullable|integer|min:0|max:100000','enabled'=>'nullable|boolean']);
        try {
            $category=ServiceCategory::create($data+['enabled'=>$data['enabled']??true]);
            try { $audit->record('catalogue.category.created',$category,['key'=>$category->key],$request); } catch (\Throwable $auditException) { report($auditException); }
            return back()->with('success','Service category created.');
        } catch (\Throwable $e) { report($e); return back()->with('error','Service category could not be created safely.'); }
    }

    public function storeService(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data=$request->validate(['category_id'=>'required|integer|exists:service_categories,id','key'=>'required|string|max:100|alpha_dash|unique:services,key','name'=>'required|string|max:160','description'=>'nullable|string|max:5000','metadata'=>'nullable|array','icon'=>'nullable|string|max:60','enabled'=>'nullable|boolean']);
        try {
            $metadata=$data['metadata']??[]; if(filled($data['icon']??null)) $metadata['icon']=$data['icon']; unset($data['icon']); $service=Service::create($data+['metadata'=>$metadata,'enabled'=>$data['enabled']??true]);
            try { $audit->record('catalogue.service.created',$service,['key'=>$service->key],$request); } catch (\Throwable $auditException) { report($auditException); }
            return back()->with('success','Service created.');
        } catch (\Throwable $e) { report($e); return back()->with('error','Service could not be created safely.'); }
    }


    public function generateServiceIcons(Request $request, AuditLogger $audit): RedirectResponse
    {
        $icons=['airtime'=>'airtime','data'=>'data','electricity'=>'electricity','cable'=>'cable','exam'=>'education','education'=>'education','sms'=>'sms','whatsapp'=>'whatsapp','payment'=>'payment','wallet'=>'wallet','bank'=>'banking','loan'=>'loan','savings'=>'savings','investment'=>'investment','market'=>'marketplace','shop'=>'shopping','gaming'=>'gaming','bet'=>'betting','internet'=>'internet','hosting'=>'hosting','domain'=>'domain','nin'=>'nin','bvn'=>'bvn','cac'=>'cac','identity'=>'identity'];
        $count=0;
        foreach(Service::query()->get() as $service){
            $metadata=$service->metadata??[];
            if(filled($metadata['icon']??null)) continue;
            $haystack=strtolower($service->key.' '.$service->name);
            $icon='default';
            foreach($icons as $needle=>$value){if(str_contains($haystack,$needle)){$icon=$value;break;}}
            $metadata['icon']=$icon;
            $service->update(['metadata'=>$metadata]);
            $count++;
        }
        $audit->record('catalogue.service_icons.generated',null,['updated'=>$count],$request);
        return back()->with('success',"Service icon assignments generated for {$count} service(s).");
    }

    public function uploadServiceIcon(Request $request, Service $service, AuditLogger $audit): RedirectResponse
    {
        $request->validate(['icon'=>'required|file|mimes:svg|max:1024']);
        $svg=(string) file_get_contents($request->file('icon')->getRealPath());
        if($svg==='' || preg_match('/<\/?(script|iframe|object|embed|foreignObject)\b|\bon[a-z]+\s*=|javascript:/i',$svg)){
            return back()->with('error','The SVG icon contains unsafe markup and was rejected.');
        }
        if(!str_contains(strtolower($svg),'<svg')) return back()->with('error','Only valid SVG icons are accepted.');
        $old=($service->metadata??[])['icon_url']??null;
        $path='service-icons/'.$service->id.'-'.bin2hex(random_bytes(8)).'.svg';
        Storage::disk('public')->put($path,$svg);
        $metadata=$service->metadata??[];
        $metadata['icon_url']=Storage::disk('public')->url($path);
        $service->update(['metadata'=>$metadata]);
        if($old && str_contains($old,'/storage/')) Storage::disk('public')->delete(str_replace('/storage/','',$old));
        $audit->record('catalogue.service_icon.imported',$service,['service_id'=>$service->id],$request);
        return back()->with('success','Service SVG icon imported.');
    }

    public function storeProduct(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data=$request->validate(['service_id'=>'required|integer|exists:services,id','key'=>'required|string|max:120','name'=>'required|string|max:200','currency'=>'required|string|size:3|regex:/^[A-Za-z]{3}$/','metadata'=>'nullable|array']);
        try {
            if(ServiceProduct::query()->where('service_id',$data['service_id'])->where('key',$data['key'])->exists()) return back()->with('error','A product with this key already exists under this service.');
            $data['currency']=strtoupper($data['currency']);
            $product=ServiceProduct::create($data+['enabled'=>false,'publication_status'=>'draft']);
            try { $audit->record('catalogue.product.created',$product,['service_id'=>$product->service_id,'key'=>$product->key],$request); } catch (\Throwable $auditException) { report($auditException); }
            return back()->with('success','Service product created.');
        } catch (\Throwable $e) { report($e); return back()->with('error','Service product could not be created safely.'); }
    }

    public function syncProvider(Request $request, ProviderCatalogueSyncService $sync): RedirectResponse|JsonResponse
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
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => 'Catalogue sync failed safely. Review the server-side diagnostics.'], 422)
                : back()->with('error','Catalogue sync failed safely. Review the server-side diagnostics.');
        }
        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => "Catalogue sync completed. {$count} product record(s) processed.", 'products_processed' => $count])
            : back()->with('success',"Catalogue sync completed. {$count} product record(s) processed.");
    }

    public function syncAllVerified(Request $request, ProviderCatalogueSyncService $sync, AuditLogger $audit): RedirectResponse|JsonResponse
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

        try { $audit->record('catalogue.sync_all_completed', null, [
            'providers_considered' => $providers->count(),
            'service_syncs_completed' => $processed,
            'products_processed' => $products,
            'failures' => $failures,
        ], $request); } catch (\Throwable $auditException) { report($auditException); }

        if ($providers->isEmpty()) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => 'No enabled, verified provider with catalogue retrieval capability is ready for sync.', 'providers_considered' => 0, 'service_syncs_completed' => 0, 'products_processed' => 0], 422)
                : back()->with('error', 'No enabled, verified provider with catalogue retrieval capability is ready for sync.');
        }

        if ($failures) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => "Catalogue sync completed with {$processed} successful service sync(s), {$products} product record(s), and ".count($failures)." failure(s).", 'providers_considered' => $providers->count(), 'service_syncs_completed' => $processed, 'products_processed' => $products, 'failures' => $failures], 207)
                : back()->with('error', "Catalogue sync completed with {$processed} successful service sync(s), {$products} product record(s), and ".count($failures)." failure(s).");
        }

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => "Catalogue sync completed successfully: {$processed} service sync(s), {$products} product record(s) processed.", 'providers_considered' => $providers->count(), 'service_syncs_completed' => $processed, 'products_processed' => $products, 'failures' => []])
            : back()->with('success', "Catalogue sync completed successfully: {$processed} service sync(s), {$products} product record(s) processed.");
    }


    public function toggleMapping(ProviderServiceMapping $mapping, AuditLogger $audit, Request $request): RedirectResponse
    {
        try {
            $result=DB::transaction(function() use($mapping): array {
                $mapping=ProviderServiceMapping::query()->lockForUpdate()->findOrFail($mapping->id);
                $provider=ApiProvider::query()->lockForUpdate()->findOrFail($mapping->api_provider_id);
                if(!$mapping->enabled && (!$provider->enabled||$provider->paused||$provider->verification_status!=='live_verified'||$provider->integration_status!=='live_verified')) return ['ok'=>false,'message'=>'A provider service mapping can only be enabled for an enabled, unpaused, live-verified provider.'];
                if (!$mapping->enabled) {
                    if (!in_array('transaction_initiation', (array) $mapping->capabilities, true)) {
                        return ['ok'=>false,'message'=>'Transaction-initiation capability must be explicitly configured before enabling this route.'];
                    }
                    $hasSourceCost = ProviderServiceProduct::query()
                        ->where('api_provider_id', $provider->id)
                        ->where('enabled', true)
                        ->whereHas('product', fn ($query) => $query->where('service_id', $mapping->service_id))
                        ->exists();
                    $hasActiveProductMapping = DB::table('provider_product_mappings_v2 as m')
                        ->join('provider_services as ps', 'ps.id', '=', 'm.provider_service_id')
                        ->join('provider_service_imports as i', function ($join): void {
                            $join->on('i.provider_service_id', '=', 'm.provider_service_id')
                                ->on('i.api_provider_id', '=', 'm.api_provider_id');
                        })
                        ->join('service_products as p', 'p.id', '=', 'm.catalogue_product_id')
                        ->where('m.api_provider_id', $provider->id)
                        ->where('m.enabled', true)
                        ->where('m.mapping_status', 'active')
                        ->where('i.approved', true)
                        ->where('i.imported', true)
                        ->where('ps.status', '!=', 'removed')
                        ->where('p.service_id', $mapping->service_id)
                        ->exists();
                    if (!$hasSourceCost || !$hasActiveProductMapping) {
                        return ['ok'=>false,'message'=>'Activate an approved product-level mapping with valid source cost before enabling this service route.'];
                    }
                }
                $enabled=!$mapping->enabled;
                $mapping->updateOrFail(['enabled'=>$enabled]);
                return ['ok'=>true,'enabled'=>$enabled,'mapping_id'=>$mapping->id,'provider_id'=>$mapping->api_provider_id,'service_id'=>$mapping->service_id];
            });
            if(!$result['ok']) return back()->with('error',$result['message']);
            try { $audit->record($result['enabled']?'catalogue.mapping.enabled':'catalogue.mapping.disabled',ProviderServiceMapping::find($result['mapping_id']),['provider_id'=>$result['provider_id'],'service_id'=>$result['service_id']],$request); } catch(\Throwable $auditException){report($auditException);}
            return back()->with('success','Provider service mapping status updated.');
        } catch(\Throwable $e){report($e);return back()->with('error','Provider service mapping update failed safely.');}
    }

    public function disableProduct(ServiceProduct $product, AuditLogger $audit, Request $request): RedirectResponse
    {
        try { DB::transaction(function () use ($product): void {
            $product = ServiceProduct::query()->lockForUpdate()->findOrFail($product->id);
            ProviderServiceProduct::query()->where('service_product_id', $product->id)->lockForUpdate()->get()->each->update(['enabled' => false]);
            $product->update(['enabled' => false, 'publication_status' => 'unpublished']);
        }); } catch (\Throwable $e) { report($e); return back()->with('error','Catalogue product could not be disabled safely.'); }
        try { $audit->record('catalogue.product.disabled', $product, ['service_id' => $product->service_id], $request); } catch (\Throwable $e) { report($e); }
        return back()->with('success','Product and provider mappings disabled.');
    }
}
