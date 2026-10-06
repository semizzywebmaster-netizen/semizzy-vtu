<?php

namespace App\Http\Controllers\Admin;

use App\Services\Providers\ProviderPresetRegistry;
use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\ProviderServiceProduct;
use App\Services\Audit\AuditLogger;
use App\Services\Providers\ProviderTestService;
use App\Services\Providers\ProviderUrlGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ProviderController extends Controller
{
    public function installPresets(ProviderPresetRegistry $registry, AuditLogger $audit, Request $request): RedirectResponse
    {
        try {
            $result = $registry->install();
            $audit->record('provider.presets.installed', null, [
                'providers' => $result['providers'],
                'services' => $result['services'],
                'mappings' => $result['mappings'],
            ], $request);

            return back()->with('success', "Provider catalogue installed: {$result['providers']} providers, {$result['services']} services and {$result['mappings']} provider mappings. Credentials remain blank and providers remain disabled until configured and verified.");
        } catch (\Throwable $e) {
            Log::warning('Provider management operation failed.', ['exception_class' => get_class($e)]);
            return back()->with('error', 'Provider preset installation failed safely. No provider credentials were changed.');
        }
    }

    public function wizard(int $provider): Response
    {
        $model = ApiProvider::query()->findOrFail($provider);
        return Inertia::render('Admin/ProviderWizard', [
            'provider' => [
                'id' => $model->id,
                'display_name' => $model->display_name,
                'environment' => $model->environment,
                'enabled' => (bool) $model->enabled,
                'verification_status' => $model->verification_status,
                'integration_status' => $model->integration_status,
            ],
        ]);
    }

    public function index(): Response
    {
        // Provider records can contain legacy encrypted endpoint/credential data
        // created before the current encryption casts were introduced. One malformed
        // legacy row must never take the entire admin provider page down with HTTP 500.
        $providers = ApiProvider::query()->latest()->get()->map(function (ApiProvider $p): array {
            $credentials = [];
            try {
                $credentials = $p->maskedCredentials();
            } catch (\Throwable $e) {
                Log::warning('Provider credential masking failed during admin listing.', [
                    'provider_id' => $p->id,
                    'exception_class' => get_class($e),
                ]);
            }

            $endpoints = [];
            try {
                $endpoints = $p->endpoints()
                    ->latest('id')
                    ->get(['id','name','operation','method','path','enabled'])
                    ->map(fn ($endpoint) => [
                        'id' => $endpoint->id,
                        'name' => $endpoint->name,
                        'operation' => $endpoint->operation,
                        'method' => $endpoint->method,
                        'path' => $endpoint->path,
                        'enabled' => (bool) $endpoint->enabled,
                    ])->values()->all();
            } catch (\Throwable $e) {
                Log::warning('Provider endpoint metadata could not be read during admin listing.', [
                    'provider_id' => $p->id,
                    'exception_class' => get_class($e),
                ]);
            }

            return [
                'id' => $p->id,
                'identifier' => $p->identifier,
                'display_name' => $p->display_name,
                'base_url' => $p->base_url,
                'documentation_url' => $p->documentation_url,
                'official_website' => $p->official_website,
                'environment' => $p->environment,
                'auth_type' => $p->auth_type,
                'verification_status' => $p->verification_status,
                'integration_status' => $p->integration_status,
                'enabled' => (bool) $p->enabled,
                'paused' => (bool) $p->paused,
                'priority' => $p->priority,
                'last_tested_at' => $p->last_tested_at?->toISOString(),
                'last_test_status' => $p->last_test_status,
                'last_test_summary' => match ($p->last_test_status) {
                    'ACCEPTED', 'SUCCESS', 'OK' => 'Connection test completed successfully.',
                    'FAILED', 'ERROR', 'REJECTED' => 'Connection test failed. Review server-side diagnostics.',
                    default => null,
                },
                'credentials' => $credentials,
                'capabilities' => is_array($p->capabilities ?? null) ? $p->capabilities : [],
                // Never serialize legacy endpoint configuration: it may contain
                // authentication headers, query parameters, webhook secrets, or URLs.
                // The admin UI only needs safe endpoint metadata.
                'endpoints' => $endpoints,
                'endpoint_count' => count($endpoints),
                'endpoint_operations' => collect($endpoints)->pluck('operation')->filter()->values()->all(),
                'service_categories' => is_array($p->service_categories ?? null) ? $p->service_categories : [],
            ];
        })->values()->all();

        return Inertia::render('Admin/Providers', [
            'providers' => $providers,
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validatedProvider($request, true);
        try {
            app(ProviderUrlGuard::class)->validate($data['base_url'] ?? null);
            $data['credentials'] = $data['credentials'] ?? [];
            $data['enabled'] = false;
            $data['paused'] = true;
            $data['verification_status'] = 'unverified';
            $data['integration_status'] = 'draft';
            $provider = ApiProvider::create($data);
            try { $audit->record('provider.created', $provider, ['identifier'=>$provider->identifier,'environment'=>$provider->environment,'enabled'=>false], $request); }
            catch (\Throwable $auditException) { Log::warning('Provider audit logging failed.', ['exception_class' => get_class($auditException)]); }
            return back()->with('success', 'Provider saved as unverified and disabled.');
        } catch (\Throwable $e) {
            Log::warning('Provider management operation failed.', ['exception_class' => get_class($e)]);
            return back()->with('error', 'Provider could not be created safely.');
        }
    }

    public function update(Request $request, int $provider, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validatedProvider($request, false);
        try {
            $model = ApiProvider::query()->findOrFail($provider);
            app(ProviderUrlGuard::class)->validate($data['base_url'] ?? $model->base_url);
            $verificationSensitiveFields = ['base_url','environment','auth_type','credentials','capabilities','endpoints','service_categories'];
            $requiresReverification = collect($verificationSensitiveFields)->contains(fn (string $field): bool => array_key_exists($field, $data) && $data[$field] !== $model->getAttribute($field));
            $model->fill($data);
            if ($requiresReverification) {
                $model->forceFill(['enabled'=>false,'paused'=>true,'verification_status'=>'unverified','integration_status'=>'draft','last_test_status'=>null,'last_test_summary'=>null]);
            }
            $model->saveOrFail();
            try { $audit->record('provider.updated', $model, ['identifier'=>$model->identifier,'updated_fields'=>array_keys($data)], $request); }
            catch (\Throwable $auditException) { Log::warning('Provider audit logging failed.', ['exception_class' => get_class($auditException)]); }
            return back()->with('success', 'Provider updated. Re-test it before enabling.');
        } catch (\Throwable $e) {
            Log::warning('Provider management operation failed.', ['exception_class' => get_class($e)]);
            return back()->with('error', 'Provider could not be updated safely.');
        }
    }

    public function test(int $provider, ProviderTestService $tester, AuditLogger $audit, Request $request): \Illuminate\Http\JsonResponse|RedirectResponse
    {
        try {
            $model = ApiProvider::query()->findOrFail($provider);
            try {
                $result = $tester->test($model);
            } catch (\Throwable $e) {
                Log::warning('Provider management operation failed.', ['exception_class' => get_class($e)]);
                $model->forceFill(['last_tested_at'=>now(),'last_test_status'=>'FAILED','last_test_summary'=>'Provider test failed safely.','enabled'=>false,'paused'=>true,'verification_status'=>'test_failed','integration_status'=>'test_failed'])->saveOrFail();
                try { $audit->record('provider.test.failed',$model,['reason'=>'transport_or_adapter_exception'],$request); } catch (\Throwable $auditException) { Log::warning('Provider audit logging failed.', ['exception_class' => get_class($auditException)]); }
                return $request->expectsJson() ? response()->json(['status'=>'FAILED','message'=>'Provider test failed safely. Review the server-side diagnostic log.'],502) : back()->with('error','Provider test failed safely. Review the server-side diagnostic log.');
            }
            if ($result['result']->accepted) {
                $verified=$model->environment==='production';
                $model->updateOrFail(['verification_status'=>$verified?'live_verified':'sandbox_verified','integration_status'=>$verified?'live_verified':'sandbox_verified','enabled'=>false,'paused'=>true]);
                try { $audit->record('provider.test.succeeded',$model,['environment'=>$model->environment,'status'=>$result['result']->status],$request); } catch (\Throwable $auditException) { Log::warning('Provider audit logging failed.', ['exception_class' => get_class($auditException)]); }
                return $request->expectsJson() ? response()->json(['status'=>'SUCCESS','message'=>'Provider health check succeeded. Provider remains disabled until explicitly enabled.','verification_status'=>$model->verification_status,'enabled'=>false],200) : back()->with('success','Provider health check succeeded. Provider remains disabled until explicitly enabled.');
            }
            $model->updateOrFail(['enabled'=>false,'paused'=>true,'verification_status'=>'test_failed','integration_status'=>'test_failed']);
            try { $audit->record('provider.test.failed',$model,['environment'=>$model->environment,'status'=>$result['result']->status],$request); } catch (\Throwable $auditException) { Log::warning('Provider audit logging failed.', ['exception_class' => get_class($auditException)]); }
            return $request->expectsJson() ? response()->json(['status'=>'FAILED','message'=>'Provider test did not succeed. Review server-side diagnostics.'],502) : back()->with('error','Provider test did not succeed. Review server-side diagnostics.');
        } catch (\Throwable $e) {
            Log::warning('Provider management operation failed.', ['exception_class' => get_class($e)]);
            return $request->expectsJson() ? response()->json(['status'=>'FAILED','message'=>'Provider test action failed safely.'],500) : back()->with('error','Provider test action failed safely.');
        }
    }

    public function bulkTest(Request $request, ProviderTestService $tester, AuditLogger $audit): \Illuminate\Http\JsonResponse|RedirectResponse
    {
        $ids = $request->validate(['provider_ids' => 'required|array|max:50', 'provider_ids.*' => 'integer|distinct'])['provider_ids'];
        $tested = $succeeded = $failed = 0;

        foreach (ApiProvider::query()->whereIn('id', $ids)->get() as $provider) {
            $tested++;
            try {
                $result = $tester->test($provider);
                if ($result['result']->accepted) {
                    $verified = $provider->environment === 'production';
                    $provider->update([
                        'verification_status' => $verified ? 'live_verified' : 'sandbox_verified',
                        'integration_status' => $verified ? 'live_verified' : 'sandbox_verified',
                        'enabled' => false,
                        'paused' => true,
                    ]);
                    $succeeded++;
                    $audit->record('provider.test.succeeded', $provider, ['bulk' => true, 'status' => $result['result']->status], $request);
                } else {
                    $provider->update(['enabled'=>false,'paused'=>true,'verification_status'=>'test_failed','integration_status'=>'test_failed']);
                    $failed++;
                    $audit->record('provider.test.failed', $provider, ['bulk'=>true,'status'=>$result['result']->status], $request);
                }
            } catch (\Throwable $e) {
                Log::warning('Provider management operation failed.', ['exception_class' => get_class($e)]);
                $provider->forceFill(['last_tested_at'=>now(),'last_test_status'=>'FAILED','last_test_summary'=>'Provider test failed safely.','enabled'=>false,'paused'=>true,'verification_status'=>'test_failed','integration_status'=>'test_failed'])->save();
                $failed++;
                try { $audit->record('provider.test.failed', $provider, ['bulk'=>true,'reason'=>'transport_or_adapter_exception'], $request); }
                catch (\Throwable $auditException) { Log::warning('Provider audit logging failed.', ['exception_class' => get_class($auditException)]); }
            }
        }

        if ($request->expectsJson()) return response()->json(['status'=>'completed','tested'=>$tested,'succeeded'=>$succeeded,'failed'=>$failed,'message'=>"Bulk provider test completed: {$tested} tested, {$succeeded} passed, {$failed} failed."]);
        return back()->with('success', "Bulk provider test completed: {$tested} tested, {$succeeded} passed, {$failed} failed.");
    }

    public function bulkToggle(Request $request, AuditLogger $audit): \Illuminate\Http\JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'provider_ids' => 'required|array|max:50',
            'provider_ids.*' => 'integer|distinct',
            'enabled' => 'required|boolean',
        ]);
        $changed = $skipped = 0;

        foreach (ApiProvider::query()->whereIn('id', $data['provider_ids'])->get() as $provider) {
            try {
                $result = DB::transaction(function () use ($provider, $data): array {
                    $locked = ApiProvider::query()->lockForUpdate()->find($provider->id);
                    if (! $locked) {
                        return ['changed' => false, 'skipped' => true, 'provider' => null];
                    }
                    if ($data['enabled'] && ($locked->verification_status !== 'live_verified' || $locked->integration_status !== 'live_verified')) {
                        return ['changed' => false, 'skipped' => true, 'provider' => $locked];
                    }
                    $locked->forceFill(['enabled'=>(bool)$data['enabled'],'paused'=>!$data['enabled']])->saveOrFail();
                    return ['changed' => true, 'skipped' => false, 'provider' => $locked];
                });
                if ($result['skipped']) { $skipped++; continue; }
                $changed++;
                try { $audit->record($data['enabled'] ? 'provider.enabled' : 'provider.disabled', $result['provider'], ['bulk'=>true], $request); }
                catch (\Throwable $auditException) { Log::warning('Provider audit logging failed.', ['exception_class' => get_class($auditException)]); }
            } catch (\Throwable $e) {
                Log::warning('Provider management operation failed.', ['exception_class' => get_class($e)]);
                $skipped++;
            }
        }

        if ($request->expectsJson()) return response()->json(['status'=>'completed','changed'=>$changed,'skipped'=>$skipped,'message'=>"Bulk provider status update completed: {$changed} changed, {$skipped} skipped."]);
        return back()->with('success', "Bulk provider status update completed: {$changed} changed, {$skipped} skipped.");
    }

    public function bulkDestroy(Request $request, AuditLogger $audit): \Illuminate\Http\JsonResponse|RedirectResponse
    {
        $ids = $request->validate([
            'provider_ids' => ['required', 'array', 'min:1', 'max:50'],
            'provider_ids.*' => ['integer', 'distinct', 'exists:api_providers,id'],
        ])['provider_ids'];

        $removed = 0;

        foreach (ApiProvider::query()->whereIn('id', $ids)->get() as $provider) {
            try {
                $result = DB::transaction(function () use ($provider): array {
                    $locked = ApiProvider::query()->lockForUpdate()->find($provider->id);
                    if (! $locked) return ['removed'=>false,'provider'=>null,'mapping_count'=>0];
                    $locked->serviceMappings()->lockForUpdate()->get()->each(fn ($mapping) => $mapping->forceFill(['enabled'=>false])->saveOrFail());
                    $providerProductMappings = ProviderServiceProduct::query()->where('api_provider_id',$locked->id)->lockForUpdate()->get();
                    $providerProductMappings->each(fn (ProviderServiceProduct $mapping) => $mapping->forceFill(['enabled'=>false])->saveOrFail());
                    $locked->forceFill(['enabled'=>false,'paused'=>true])->saveOrFail();
                    return ['removed'=>true,'provider'=>$locked,'mapping_count'=>$providerProductMappings->count()];
                });
                if (!$result['removed']) { continue; }
                try {
                    $audit->record('provider.removed', $result['provider'], ['bulk'=>true,'history_preserved'=>true,'archived'=>true,'mappings_disabled'=>true,'provider_product_mappings_disabled'=>$result['mapping_count']], $request);
                } catch (\Throwable $auditException) { Log::warning('Provider audit logging failed.', ['exception_class' => get_class($auditException)]); }
                $removed++;
            } catch (\Throwable $e) {
                Log::warning('Provider management operation failed.', ['exception_class' => get_class($e)]);
            }
        }

        if ($request->expectsJson()) return response()->json(['status'=>'completed','removed'=>$removed,'message'=>"Bulk provider removal completed: {$removed} provider(s) safely archived and disabled."]);
        return back()->with('success', "Bulk provider removal completed: {$removed} provider(s) safely archived and disabled.");
    }

    public function toggle(int $provider, AuditLogger $audit, Request $request): \Illuminate\Http\JsonResponse|RedirectResponse
    {
        try {
            $result = DB::transaction(function () use ($provider): array {
                $providerModel = ApiProvider::query()
                    ->lockForUpdate()
                    ->findOrFail($provider);

                if (
                    ! $providerModel->enabled
                    && (
                        $providerModel->verification_status !== 'live_verified'
                        || $providerModel->integration_status !== 'live_verified'
                    )
                ) {
                    return [
                        'ok' => false,
                        'enabled' => false,
                        'message' => 'Provider must be live-verified before it can be enabled.',
                    ];
                }

                $enabled = ! (bool) $providerModel->enabled;

                $providerModel->forceFill([
                    'enabled' => $enabled,
                    'paused' => ! $enabled,
                ])->saveOrFail();

                return [
                    'ok' => true,
                    'enabled' => $enabled,
                    'provider_id' => $providerModel->id,
                    'identifier' => $providerModel->identifier,
                ];
            });

            if (! $result['ok']) {
                return $request->expectsJson() ? response()->json(['status'=>'rejected','message'=>$result['message']],422) : back()->with('error', $result['message']);
            }

            // Audit after the state change commits. A logging/schema problem must
            // never turn a successful provider status change into a HTTP 500.
            try {
                $providerModel = ApiProvider::query()->find($result['provider_id']);
                $audit->record(
                    $result['enabled'] ? 'provider.enabled' : 'provider.disabled',
                    $providerModel,
                    ['identifier' => $result['identifier']],
                    $request
                );
            } catch (\Throwable $auditException) {
                Log::warning('Provider audit logging failed.', ['exception_class' => get_class($auditException)]);
            }

            return $request->expectsJson() ? response()->json(['status'=>'updated','enabled'=>$result['enabled'],'message'=>$result['enabled'] ? 'Provider enabled successfully.' : 'Provider disabled successfully.']) : back()->with(
                'success',
                $result['enabled'] ? 'Provider enabled successfully.' : 'Provider disabled successfully.'
            );
        } catch (\Throwable $e) {
            Log::warning('Provider management operation failed.', ['exception_class' => get_class($e)]);

            $message = 'Provider status update failed safely. Check the provider record and server error log.';
            return $request->expectsJson()
                ? response()->json(['status' => 'FAILED', 'message' => $message], 500)
                : back()->with('error', $message);
        }
    }

    public function destroy(int $provider, AuditLogger $audit, Request $request): \Illuminate\Http\JsonResponse|RedirectResponse
    {
        try {
            $result = DB::transaction(function () use ($provider): array {
                $model = ApiProvider::query()->lockForUpdate()->findOrFail($provider);
                $model->serviceMappings()->lockForUpdate()->get()->each(
                    fn ($mapping) => $mapping->forceFill(['enabled' => false])->saveOrFail()
                );
                $productMappings = ProviderServiceProduct::query()
                    ->where('api_provider_id', $model->id)
                    ->lockForUpdate()
                    ->get();
                $productMappings->each(
                    fn (ProviderServiceProduct $mapping) => $mapping->forceFill(['enabled' => false])->saveOrFail()
                );
                $model->forceFill(['enabled' => false, 'paused' => true])->saveOrFail();
                $model->delete();
                return [
                    'provider_id' => $model->id,
                    'identifier' => $model->identifier,
                    'mapping_count' => $productMappings->count(),
                ];
            });

            try {
                $audit->record(
                    'provider.removed',
                    ApiProvider::withTrashed()->find($result['provider_id']),
                    [
                        'identifier' => $result['identifier'],
                        'history_preserved' => true,
                        'archived' => true,
                        'mappings_disabled' => true,
                        'provider_product_mappings_disabled' => $result['mapping_count'],
                    ],
                    $request
                );
            } catch (\Throwable $auditException) {
                Log::warning('Provider audit logging failed.', ['exception_class' => get_class($auditException)]);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'SUCCESS',
                    'message' => 'Provider removed from the active registry. Historical records are retained.',
                ]);
            }

            return back()->with('success', 'Provider removed from the active registry. Historical records are retained.');
        } catch (\Throwable $e) {
            Log::warning('Provider management operation failed.', ['exception_class' => get_class($e)]);
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'FAILED',
                    'message' => 'Provider could not be removed safely.',
                ], 500);
            }
            return back()->with('error', 'Provider could not be removed safely.');
        }
    }

    private function validatedProvider(Request $request, bool $creating): array
    {
        $rules = [
            'display_name' => ($creating ? 'required' : 'sometimes|required').'|string|max:160',
            'base_url' => 'nullable|url:http,https|max:500',
            'documentation_url' => 'nullable|url:http,https|max:500',
            'official_website' => 'nullable|url:http,https|max:500',
            'environment' => ($creating ? 'required' : 'sometimes|required').'|in:sandbox,production',
            'auth_type' => ($creating ? 'required' : 'sometimes|required').'|string|max:80|regex:/^[A-Za-z0-9._:-]+$/',
            'capabilities' => 'nullable|array',
            'endpoints' => 'nullable|array',
            'service_categories' => 'nullable|array',
            'credentials' => 'nullable|array',
            'priority' => 'nullable|integer|min:0|max:100000',
        ];

        if ($creating) {
            $rules['identifier'] = 'required|string|max:100|alpha_dash|unique:api_providers,identifier';
        }

        return $request->validate($rules);
    }
}
