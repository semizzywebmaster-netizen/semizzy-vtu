<?php

namespace Semizzy\Addons\ApiProviderPlatform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\ProviderServiceMapping;
use App\Models\ProviderService;
use App\Models\ProviderServiceImport;
use App\Models\ProviderProductMappingV2;
use App\Models\Service;
use App\Models\ServiceProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Semizzy\Addons\ApiProviderPlatform\Services\ProductPublicationService;
use App\Services\Audit\AuditLogger;
use Inertia\Inertia;
use Inertia\Response;

final class ProviderPlatformAdminController extends Controller
{
    public function index(): Response
    {
        $providerColumns = [
            'id', 'identifier', 'display_name', 'verification_status', 'integration_status',
            'enabled', 'paused', 'last_tested_at', 'last_test_status',
            'last_successful_request_at', 'capabilities', 'service_categories',
        ];

        $providers = ApiProvider::query()
            ->orderBy('display_name')
            ->get($providerColumns)
            ->map(fn (ApiProvider $provider): array => $this->providerSummary($provider))
            ->values();

        $services = Service::query()->with('category')->orderBy('name')->get();
        $mappings = ProviderServiceMapping::query()
            ->with(['provider:' . implode(',', $providerColumns)])
            ->get();

        $serviceRows = $services->map(function (Service $service) use ($mappings): array {
            $related = $mappings->filter(function (ProviderServiceMapping $mapping) use ($service): bool {
                if ($mapping->service_id !== null) {
                    return (int) $mapping->service_id === (int) $service->id;
                }

                return (string) $mapping->service_key === (string) $service->key;
            });

            $mappedProviders = $related
                ->filter(fn (ProviderServiceMapping $mapping): bool => $mapping->provider !== null)
                ->unique('api_provider_id')
                ->values();

            $liveProviders = $related->filter(function (ProviderServiceMapping $mapping): bool {
                $provider = $mapping->provider;

                return $provider !== null
                    && $mapping->enabled
                    && $provider->enabled
                    && !$provider->paused
                    && $provider->verification_status === 'live_verified'
                    && $provider->integration_status === 'live_verified';
            })->unique('api_provider_id')->values();

            return [
                'id' => $service->id,
                'key' => $service->key,
                'name' => $service->name,
                'category' => $service->category?->name ?? 'Uncategorised',
                'enabled' => (bool) $service->enabled,
                'mapped_provider_count' => $mappedProviders->count(),
                'live_verified_provider_count' => $liveProviders->count(),
                'providers' => $mappedProviders->map(function (ProviderServiceMapping $mapping): array {
                    $provider = $mapping->provider;

                    return [
                        'id' => $provider->id,
                        'identifier' => $provider->identifier,
                        'display_name' => $provider->display_name,
                        'verification_status' => $provider->verification_status,
                        'integration_status' => $provider->integration_status,
                        'enabled' => (bool) $provider->enabled,
                        'paused' => (bool) $provider->paused,
                        'mapping_enabled' => (bool) $mapping->enabled,
                        'last_tested_at' => $provider->last_tested_at?->toISOString(),
                        'last_test_status' => $provider->last_test_status,
                    ];
                })->values()->all(),
            ];
        })->values();

        $liveVerifiedProviders = $providers->filter(
            fn (array $provider): bool => $provider['verification_status'] === 'live_verified'
                && $provider['integration_status'] === 'live_verified'
        )->count();

        $productionEligibleProviders = $providers->filter(
            fn (array $provider): bool => $provider['verification_status'] === 'live_verified'
                && $provider['integration_status'] === 'live_verified'
                && $provider['enabled']
                && !$provider['paused']
        )->count();

        $enabledServices = $serviceRows->filter(fn (array $service): bool => $service['enabled']);
        $coveredEnabledServices = $enabledServices->filter(
            fn (array $service): bool => $service['live_verified_provider_count'] > 0
        )->count();

        return Inertia::render('Admin/ProviderPlatformCoverage', [
            'summary' => [
                'total_providers' => $providers->count(),
                'live_verified_providers' => $liveVerifiedProviders,
                'production_eligible_providers' => $productionEligibleProviders,
                'enabled_services' => $enabledServices->count(),
                'enabled_services_with_live_coverage' => $coveredEnabledServices,
                'enabled_services_without_live_coverage' => $enabledServices->count() - $coveredEnabledServices,
            ],
            'providers' => $providers,
            'services' => $serviceRows,
        ]);
    }

    public function cataloguePage(Request $request): Response
    {
        $filters = $request->validate([
            'provider_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'in:awaiting_approval,reviewed,approved,blocked,imported'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $query = ProviderService::query()
            ->with([
                'provider:id,identifier,display_name,verification_status,integration_status,enabled,paused',
                'category:id,external_name',
                'imports',
            ])
            ->orderBy('api_provider_id')
            ->orderBy('name');

        if (!empty($filters['provider_id'])) {
            $query->where('api_provider_id', $filters['provider_id']);
        }
        if (!empty($filters['status'])) {
            $query->whereHas('imports', fn ($builder) => $builder->where('state', $filters['status']));
        }
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhere('external_service_code', 'like', '%' . $search . '%')
                    ->orWhere('external_service_id', 'like', '%' . $search . '%');
            });
        }

        $page = $query->paginate(50)->withQueryString();

        return Inertia::render('Admin/ProviderPlatformCatalogue', [
            'services' => $page->getCollection()->map(function (ProviderService $service): array {
                $selection = $service->imports->first();

                return [
                    'id' => $service->id,
                    'provider_id' => $service->api_provider_id,
                    'provider' => $service->provider?->display_name ?? 'Unknown provider',
                    'provider_verification_status' => $service->provider?->verification_status ?? 'unknown',
                    'provider_integration_status' => $service->provider?->integration_status ?? 'unknown',
                    'provider_enabled' => (bool) $service->provider?->enabled,
                    'provider_paused' => (bool) $service->provider?->paused,
                    'external_service_id' => $service->external_service_id,
                    'external_service_code' => $service->external_service_code,
                    'name' => $service->name,
                    'category' => $service->category?->external_name,
                    'service_type' => $service->service_type,
                    'network' => $service->network,
                    'source_price' => $service->provider_price,
                    'currency' => $service->currency,
                    'source_synced_at' => $service->last_synced_at?->toISOString(),
                    'catalogue_status' => $service->status,
                    'selection_state' => $selection?->state,
                    'selected_for_review' => (bool) $selection,
                    'approved_for_import' => (bool) ($selection?->approved),
                    'imported' => (bool) ($selection?->imported),
                    'auto_sync_allowed' => (bool) ($selection?->auto_sync_allowed),
                ];
            })->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'filters' => [
                'provider_id' => $filters['provider_id'] ?? '',
                'status' => $filters['status'] ?? '',
                'search' => $filters['search'] ?? '',
            ],
            'providers' => ApiProvider::query()->orderBy('display_name')->get(['id', 'display_name']),
            'products' => ServiceProduct::query()->with('service:id,name,key')->orderBy('name')->limit(500)->get(['id', 'service_id', 'key', 'name', 'currency', 'enabled']),
            'canManage' => $request->user()?->role === 'ADMIN',
            'safety_note' => 'Discovered catalogue data is not verified capability evidence. Selection does not import, publish, or enable routing.',
        ]);
    }

    public function catalogue(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'provider_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'in:awaiting_approval,reviewed,approved,blocked,imported'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $query = ProviderService::query()
            ->with(['provider:id,identifier,display_name,verification_status,integration_status,enabled,paused', 'category:id,external_name'])
            ->with('imports')
            ->orderBy('api_provider_id')
            ->orderBy('name');

        if (!empty($filters['provider_id'])) {
            $query->where('api_provider_id', $filters['provider_id']);
        }
        if (!empty($filters['status'])) {
            $query->whereHas('imports', fn ($builder) => $builder->where('state', $filters['status']));
        }
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhere('external_service_code', 'like', '%' . $search . '%')
                    ->orWhere('external_service_id', 'like', '%' . $search . '%');
            });
        }

        $page = $query->paginate(50)->withQueryString();

        return response()->json([
            'data' => $page->getCollection()->map(function (ProviderService $service): array {
                $selection = $service->imports->first();

                return [
                    'id' => $service->id,
                    'provider_id' => $service->api_provider_id,
                    'provider' => $service->provider?->display_name,
                    'provider_verification_status' => $service->provider?->verification_status,
                    'provider_integration_status' => $service->provider?->integration_status,
                    'provider_enabled' => (bool) $service->provider?->enabled,
                    'provider_paused' => (bool) $service->provider?->paused,
                    'external_service_id' => $service->external_service_id,
                    'external_service_code' => $service->external_service_code,
                    'name' => $service->name,
                    'category' => $service->category?->external_name,
                    'service_type' => $service->service_type,
                    'network' => $service->network,
                    'source_price' => $service->provider_price,
                    'currency' => $service->currency,
                    'source_synced_at' => $service->last_synced_at?->toISOString(),
                    'catalogue_status' => $service->status,
                    'selection_state' => $selection?->state,
                    'selected_for_review' => (bool) $selection,
                    'approved_for_import' => (bool) ($selection?->approved),
                    'imported' => (bool) ($selection?->imported),
                    'auto_sync_allowed' => (bool) ($selection?->auto_sync_allowed),
                ];
            })->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'safety_note' => 'Catalogue entries are provider-sourced discovery records. Selection does not publish a product, enable routing, or certify provider capability.',
        ]);
    }

    public function selectCatalogueService(Request $request, ProviderService $providerService, AuditLogger $auditLogger): JsonResponse
    {
        $validated = $request->validate([
            'selection_scope' => ['sometimes', 'string', 'in:product,service,category'],
        ]);

        $selection = ProviderServiceImport::query()->firstOrCreate(
            [
                'api_provider_id' => $providerService->api_provider_id,
                'provider_service_id' => $providerService->id,
            ],
            [
                'selection_scope' => $validated['selection_scope'] ?? 'product',
                'imported' => false,
                'approved' => false,
                'auto_sync_allowed' => false,
                'state' => 'awaiting_approval',
                'last_imported_at' => null,
            ],
        );

        if ($selection->wasRecentlyCreated) {
            $auditLogger->record('provider_platform.catalogue_service_selected', $providerService, [
                'provider_service_id' => $providerService->id,
                'api_provider_id' => $providerService->api_provider_id,
                'selection_scope' => $selection->selection_scope,
                'state' => $selection->state,
                'approved' => false,
                'auto_sync_allowed' => false,
            ]);
        }

        if ($request->header('X-Inertia')) {
            return back()->with('status', $selection->wasRecentlyCreated
                ? 'Catalogue entry added to the review queue.'
                : 'This entry already has a review record; its existing state was preserved.');
        }

        return response()->json([
            'status' => $selection->state,
            'message' => $selection->wasRecentlyCreated
                ? 'Provider catalogue entry selected for review. It has not been imported, published, or enabled for routing.'
                : 'This catalogue entry already has a review record. Its approval, import, and sync settings were left unchanged.',
            'selection' => [
                'id' => $selection->id,
                'provider_service_id' => $selection->provider_service_id,
                'selection_scope' => $selection->selection_scope,
                'state' => $selection->state,
                'approved' => (bool) $selection->approved,
                'imported' => (bool) $selection->imported,
                'auto_sync_allowed' => (bool) $selection->auto_sync_allowed,
            ],
        ], $selection->wasRecentlyCreated ? 202 : 200);
    }

    public function approveCatalogueService(Request $request, ProviderService $providerService, AuditLogger $auditLogger): JsonResponse|\\Inertia\\Response|\\Illuminate\\Http\\RedirectResponse
    {
        $selection = ProviderServiceImport::query()
            ->where('api_provider_id', $providerService->api_provider_id)
            ->where('provider_service_id', $providerService->id)
            ->first();

        if (!$selection) {
            return response()->json(['message' => 'Select this catalogue entry for review before approval.'], 409);
        }
        if ($selection->state === 'blocked') {
            return response()->json(['message' => 'A blocked catalogue entry cannot be approved.'], 422);
        }

        $changed = !$selection->approved || $selection->state !== 'approved';
        $selection->forceFill([
            'approved' => true,
            'state' => 'approved',
            'imported' => (bool) $selection->imported,
            'auto_sync_allowed' => false,
        ])->save();

        if ($changed) {
            $auditLogger->record('provider_platform.catalogue_service_approved', $providerService, [
                'provider_service_id' => $providerService->id,
                'api_provider_id' => $providerService->api_provider_id,
                'selection_id' => $selection->id,
                'approved' => true,
                'imported' => (bool) $selection->imported,
                'auto_sync_allowed' => false,
            ]);
        }

        if ($request->header('X-Inertia')) {
            return back()->with('status', 'Catalogue entry approved for mapping review; it has not been imported or enabled.');
        }

        return response()->json([
            'status' => 'approved',
            'imported' => (bool) $selection->imported,
            'auto_sync_allowed' => false,
            'message' => 'Approved for mapping review only. No product was imported or enabled.',
        ]);
    }

    public function mapCatalogueService(Request $request, ProviderService $providerService, AuditLogger $auditLogger): JsonResponse|\\Inertia\\Response|\\Illuminate\\Http\\RedirectResponse
    {
        $validated = $request->validate([
            'catalogue_product_id' => ['required', 'integer', 'exists:service_products,id'],
        ]);

        $selection = ProviderServiceImport::query()
            ->where('api_provider_id', $providerService->api_provider_id)
            ->where('provider_service_id', $providerService->id)
            ->first();

        if (!$selection || !$selection->approved || $selection->state !== 'approved') {
            return response()->json(['message' => 'Approve this catalogue entry before mapping it.'], 409);
        }

        $attributes = [
            'api_provider_id' => $providerService->api_provider_id,
            'provider_service_id' => $providerService->id,
            'catalogue_product_id' => (int) $validated['catalogue_product_id'],
            'catalogue_product_type' => 'service_product',
        ];
        $mapping = ProviderProductMappingV2::query()->firstOrNew($attributes);
        $isNew = !$mapping->exists;
        $wasPending = $mapping->mapping_status !== 'mapped';
        if ($isNew) {
            $mapping->fill([
                'priority' => 100,
                'enabled' => false,
                'mapping_status' => 'mapped',
                'metadata' => ['created_by' => $request->user()?->id],
            ]);
        } elseif ($wasPending) {
            $mapping->mapping_status = 'mapped';
        }
        $mapping->save();

        if ($isNew || $wasPending) {
            $auditLogger->record('provider_platform.catalogue_service_mapped', $providerService, [
                'provider_service_id' => $providerService->id,
                'api_provider_id' => $providerService->api_provider_id,
                'catalogue_product_id' => (int) $validated['catalogue_product_id'],
                'mapping_id' => $mapping->id,
                'mapping_status' => $mapping->mapping_status,
                'enabled' => (bool) $mapping->enabled,
            ]);
        }

        if ($request->header('X-Inertia')) {
            return back()->with('status', 'Catalogue mapping saved disabled. Routing remains unchanged.');
        }

        return response()->json([
            'status' => 'mapped',
            'mapping_id' => $mapping->id,
            'enabled' => (bool) $mapping->enabled,
            'message' => 'Mapping saved; provider routing was not enabled.',
        ], $isNew ? 201 : 200);
    }

    public function publishProduct(Request $request, ServiceProduct $product, ProductPublicationService $publication): JsonResponse
    {
        $result = $publication->publish($product, $request->user()?->id);

        if (!$result['published']) {
            return response()->json([
                'status' => 'blocked',
                'message' => 'This product is not ready to be added to My Services.',
                'blockers' => $result['blockers'],
                'product' => [
                    'id' => $result['product']->id,
                    'publication_status' => $result['product']->publication_status,
                    'enabled' => (bool) $result['product']->enabled,
                ],
            ], 422);
        }

        return response()->json([
            'status' => 'published',
            'message' => 'Product added to My Services.',
            'product' => [
                'id' => $result['product']->id,
                'publication_status' => $result['product']->publication_status,
                'enabled' => (bool) $result['product']->enabled,
                'published_at' => $result['product']->published_at?->toISOString(),
            ],
        ]);
    }

    public function unpublishProduct(Request $request, ServiceProduct $product, ProductPublicationService $publication): JsonResponse
    {
        $updated = $publication->unpublish($product, $request->user()?->id);

        return response()->json([
            'status' => 'unpublished',
            'message' => 'Product unpublished. Existing transactions and provider mappings were preserved.',
            'product' => [
                'id' => $updated->id,
                'publication_status' => $updated->publication_status,
                'enabled' => (bool) $updated->enabled,
            ],
        ]);
    }

    private function providerSummary(ApiProvider $provider): array
    {
        return [
            'id' => $provider->id,
            'identifier' => $provider->identifier,
            'display_name' => $provider->display_name,
            'verification_status' => $provider->verification_status,
            'integration_status' => $provider->integration_status,
            'enabled' => (bool) $provider->enabled,
            'paused' => (bool) $provider->paused,
            'last_tested_at' => $provider->last_tested_at?->toISOString(),
            'last_test_status' => $provider->last_test_status,
            'last_successful_request_at' => $provider->last_successful_request_at?->toISOString(),
            'capabilities' => array_values(array_filter($provider->capabilities ?? [], 'is_string')),
            'service_categories' => array_values(array_filter($provider->service_categories ?? [], 'is_string')),
        ];
    }
}
