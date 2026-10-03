<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Services\Audit\AuditLogger;
use App\Services\Providers\ProviderTestService;
use App\Services\Providers\ProviderUrlGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProviderController extends Controller
{
    public function index(): Response
    {
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

    public function update(Request $request, ApiProvider $provider, AuditLogger $audit): RedirectResponse
    {
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

    public function test(ApiProvider $provider, ProviderTestService $tester, AuditLogger $audit, Request $request): RedirectResponse
    {
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

    public function toggle(ApiProvider $provider, AuditLogger $audit, Request $request): RedirectResponse
    {
        return DB::transaction(function () use ($provider, $audit, $request): RedirectResponse {
            $provider = ApiProvider::query()->lockForUpdate()->findOrFail($provider->id);

            if (! $provider->enabled && ($provider->verification_status !== 'live_verified' || $provider->integration_status !== 'live_verified')) {
                return back()->with('error', 'Provider must be live-verified before it can be enabled.');
            }

            $enabled = ! $provider->enabled;
            $provider->update(['enabled' => $enabled, 'paused' => ! $enabled]);
            $audit->record($enabled ? 'provider.enabled' : 'provider.disabled', $provider, [
                'identifier' => $provider->identifier,
            ], $request);

            return back()->with('success', 'Provider status updated.');
        });
    }

    public function destroy(ApiProvider $provider, AuditLogger $audit, Request $request): RedirectResponse
    {
        DB::transaction(function () use ($provider, $audit, $request): void {
            $provider = ApiProvider::query()->lockForUpdate()->findOrFail($provider->id);

            $provider->serviceMappings()->lockForUpdate()->get()->each(function ($mapping): void {
                $mapping->forceFill(['enabled' => false])->save();
            });

            $provider->forceFill(['enabled' => false, 'paused' => true])->save();
            $audit->record('provider.removed', $provider, [
                'identifier' => $provider->identifier,
                'history_preserved' => true,
                'mappings_disabled' => true,
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
