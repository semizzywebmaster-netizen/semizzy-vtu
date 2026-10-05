<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\ProviderServiceProduct;
use App\Services\Audit\AuditLogger;
use App\Services\Providers\ProviderTestService;
use App\Services\Providers\ProviderPresetRegistry;
use App\Services\Providers\ProviderUrlGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            report($e);
            return back()->with('error', 'Provider preset installation failed safely. No provider credentials were changed.');
        }
    }

    public function index(ProviderPresetRegistry $registry): Response
    {
        // Always reconcile the built-in registry before rendering. The installer is
        // idempotent and preserves administrator-entered credentials and status.
        // This also repairs deployments where only part of the preset catalogue was
        // previously imported.
        try {
            $registry->install();
        } catch (\Throwable $e) {
            report($e);
        }

        return Inertia::render('Admin/Providers', [
            'providers' => ApiProvider::query()->latest()->get()->map(fn (ApiProvider $p) => [
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
                'enabled' => $p->enabled,
                'paused' => $p->paused,
                'priority' => $p->priority,
                'last_tested_at' => $p->last_tested_at?->toISOString(),
                'last_test_status' => $p->last_test_status,
                'last_test_summary' => match ($p->last_test_status) {
                    'ACCEPTED', 'SUCCESS', 'OK' => 'Connection test completed successfully.',
                    'FAILED', 'ERROR', 'REJECTED' => 'Connection test failed. Review server-side diagnostics.',
                    default => null,
                },
                'credentials' => $p->maskedCredentials(),
                'capabilities' => $p->capabilities ?? [],
                'endpoints' => $p->endpoints ?? [],
                'service_categories' => $p->service_categories ?? [],
            ]),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validatedProvider($request, true);
        app(ProviderUrlGuard::class)->validate($data['base_url'] ?? null);
        $data['credentials'] = $data['credentials'] ?? [];
        $data['enabled'] = false;
        $data['paused'] = true;
        $data['verification_status'] = 'unverified';
        $data['integration_status'] = 'draft';

        $provider = ApiProvider::create($data);
        $audit->record('provider.created', $provider, [
            'identifier' => $provider->identifier,
            'environment' => $provider->environment,
            'enabled' => false,
        ], $request);

        return back()->with('success', 'Provider saved as unverified and disabled.');
    }

    public function update(Request $request, int $provider, AuditLogger $audit): RedirectResponse
    {
        $provider = ApiProvider::query()->findOrFail($provider);
        $data = $this->validatedProvider($request, false);
        app(ProviderUrlGuard::class)->validate($data['base_url'] ?? $provider->base_url);

        $verificationSensitiveFields = ['base_url', 'environment', 'auth_type', 'credentials', 'capabilities', 'endpoints', 'service_categories'];
        $requiresReverification = collect($verificationSensitiveFields)
            ->contains(fn (string $field): bool => array_key_exists($field, $data) && $data[$field] !== $provider->getAttribute($field));

        $provider->fill($data);

        if ($requiresReverification) {
            $provider->forceFill([
                'enabled' => false,
                'paused' => true,
                'verification_status' => 'unverified',
                'integration_status' => 'draft',
                'last_test_status' => null,
                'last_test_summary' => null,
            ]);
        }

        $provider->save();
        $audit->record('provider.updated', $provider, [
            'identifier' => $provider->identifier,
            'updated_fields' => array_keys($data),
        ], $request);

        return back()->with('success', 'Provider updated. Re-test it before enabling.');
    }

    public function test(int $provider, ProviderTestService $tester, AuditLogger $audit, Request $request): RedirectResponse
    {
        $provider = ApiProvider::query()->findOrFail($provider);
        try {
            $result = $tester->test($provider);
        } catch (\Throwable $e) {
            $provider->forceFill([
                'last_tested_at' => now(),
                'last_test_status' => 'FAILED',
                'last_test_summary' => 'Provider test failed safely.',
                'enabled' => false,
                'paused' => true,
                'verification_status' => 'test_failed',
                'integration_status' => 'test_failed',
            ])->save();
            $audit->record('provider.test.failed', $provider, ['reason' => 'transport_or_adapter_exception'], $request);

            return back()->with('error', 'Provider test failed safely. Review the server-side diagnostic log.');
        }

        if ($result['result']->accepted) {
            $verified = $provider->environment === 'production';
            $provider->update([
                'verification_status' => $verified ? 'live_verified' : 'sandbox_verified',
                'integration_status' => $verified ? 'live_verified' : 'sandbox_verified',
                'enabled' => false,
                'paused' => true,
            ]);
            $audit->record('provider.test.succeeded', $provider, [
                'environment' => $provider->environment,
                'status' => $result['result']->status,
            ], $request);

            return back()->with('success', 'Provider health check succeeded. Provider remains disabled until explicitly enabled.');
        }

        $provider->update([
            'enabled' => false,
            'paused' => true,
            'verification_status' => 'test_failed',
            'integration_status' => 'test_failed',
        ]);
        $audit->record('provider.test.failed', $provider, [
            'environment' => $provider->environment,
            'status' => $result['result']->status,
        ], $request);

        return back()->with('error', 'Provider test did not succeed. Review server-side diagnostics.');
    }

    public function bulkTest(Request $request, ProviderTestService $tester, AuditLogger $audit): RedirectResponse
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
                report($e);
                $provider->forceFill(['last_tested_at'=>now(),'last_test_status'=>'FAILED','last_test_summary'=>'Provider test failed safely.','enabled'=>false,'paused'=>true,'verification_status'=>'test_failed','integration_status'=>'test_failed'])->save();
                $failed++;
                $audit->record('provider.test.failed', $provider, ['bulk'=>true,'reason'=>'transport_or_adapter_exception'], $request);
            }
        }

        return back()->with('success', "Bulk provider test completed: {$tested} tested, {$succeeded} passed, {$failed} failed.");
    }

    public function bulkToggle(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'provider_ids' => 'required|array|max:50',
            'provider_ids.*' => 'integer|distinct',
            'enabled' => 'required|boolean',
        ]);
        $changed = $skipped = 0;

        DB::transaction(function () use ($data, $audit, $request, &$changed, &$skipped): void {
            foreach (ApiProvider::query()->whereIn('id', $data['provider_ids'])->lockForUpdate()->get() as $provider) {
                if ($data['enabled'] && ($provider->verification_status !== 'live_verified' || $provider->integration_status !== 'live_verified')) {
                    $skipped++;
                    continue;
                }
                $provider->update(['enabled'=>$data['enabled'],'paused'=>!$data['enabled']]);
                $changed++;
                $audit->record($data['enabled'] ? 'provider.enabled' : 'provider.disabled', $provider, ['bulk'=>true], $request);
            }
        });

        return back()->with('success', "Bulk provider status update completed: {$changed} changed, {$skipped} skipped.");
    }

    public function bulkDestroy(Request $request, AuditLogger $audit): RedirectResponse
    {
        $ids = $request->validate([
            'provider_ids' => ['required', 'array', 'min:1', 'max:50'],
            'provider_ids.*' => ['integer', 'distinct', 'exists:api_providers,id'],
        ])['provider_ids'];

        $removed = 0;

        DB::transaction(function () use ($ids, $audit, $request, &$removed): void {
            foreach (ApiProvider::query()->whereIn('id', $ids)->lockForUpdate()->get() as $provider) {
                $provider->serviceMappings()->lockForUpdate()->get()->each(
                    fn ($mapping) => $mapping->forceFill(['enabled' => false])->save()
                );

                $providerProductMappings = ProviderServiceProduct::query()
                    ->where('api_provider_id', $provider->id)
                    ->lockForUpdate()
                    ->get();

                $providerProductMappings->each(
                    fn (ProviderServiceProduct $mapping) => $mapping->forceFill(['enabled' => false])->save()
                );

                // Providers are financial/integration history, so "Remove" is a safe
                // archive operation rather than a hard delete that can violate FKs.
                // The row remains available for audit/history and can be reconfigured.
                $provider->forceFill([
                    'enabled' => false,
                    'paused' => true,
                ])->save();

                $audit->record('provider.removed', $provider, [
                    'bulk' => true,
                    'history_preserved' => true,
                    'archived' => true,
                    'mappings_disabled' => true,
                    'provider_product_mappings_disabled' => $providerProductMappings->count(),
                ], $request);

                $removed++;
            }
        });

        return back()->with('success', "Bulk provider removal completed: {$removed} provider(s) safely archived and disabled.");
    }

    public function toggle(int $provider, AuditLogger $audit, Request $request): RedirectResponse
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
                return back()->with('error', $result['message']);
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
                report($auditException);
            }

            return back()->with(
                'success',
                $result['enabled'] ? 'Provider enabled successfully.' : 'Provider disabled successfully.'
            );
        } catch (\\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'Provider status update failed safely. Check the provider record and server error log.'
            );
        }
    }

    public function destroy(int $provider, AuditLogger $audit, Request $request): RedirectResponse
    {
        DB::transaction(function () use ($provider, $audit, $request): void {
            $provider = ApiProvider::query()->lockForUpdate()->findOrFail($provider);

            $provider->serviceMappings()->lockForUpdate()->get()->each(function ($mapping): void {
                $mapping->forceFill(['enabled' => false])->save();
            });

            $providerProductMappings = ProviderServiceProduct::query()
                ->where('api_provider_id', $provider->id)
                ->lockForUpdate()
                ->get();
            $providerProductMappings->each(function (ProviderServiceProduct $mapping): void {
                $mapping->forceFill(['enabled' => false])->save();
            });

            $provider->forceFill(['enabled' => false, 'paused' => true])->save();
            $audit->record('provider.removed', $provider, [
                'identifier' => $provider->identifier,
                'history_preserved' => true,
                'mappings_disabled' => true,
                'provider_product_mappings_disabled' => $providerProductMappings->count(),
            ], $request);
            $provider->delete();
        });

        return back()->with('success', 'Provider removed from the active registry. Historical records are retained.');
    }

    private function validatedProvider(Request $request, bool $creating): array
    {
        $rules = [
            'display_name' => ($creating ? 'required' : 'sometimes|required').'|string|max:160',
            'base_url' => 'nullable|url:http,https|max:500',
            'documentation_url' => 'nullable|url:http,https|max:500',
            'official_website' => 'nullable|url:http,https|max:500',
            'environment' => ($creating ? 'required' : 'sometimes|required').'|in:sandbox,production',
            'auth_type' => ($creating ? 'required' : 'sometimes|required').'|in:custom,bearer,basic,api_key_header',
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
