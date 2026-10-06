<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Services\Addons\AddonLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AddonController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Addons', [
            'addons' => Addon::query()
                ->with(['lifecycleEvents' => fn ($query) => $query->latest()->limit(8)])
                ->latest()
                ->get()
                ->map(fn (Addon $addon) => [
                    'id' => $addon->id,
                    'identifier' => $addon->identifier,
                    'name' => $addon->name,
                    'version' => $addon->version,
                    'status' => $addon->status,
                    'last_error' => $addon->last_error,
                    'dependencies' => $addon->dependencies ?? [],
                    'permissions' => $addon->permissions ?? [],
                    'installed_at' => $addon->installed_at?->toISOString(),
                    'activated_at' => $addon->activated_at?->toISOString(),
                    'events' => $addon->lifecycleEvents->map(fn ($event) => [
                        'id' => $event->id,
                        'event' => $event->event,
                        'from_status' => $event->from_status,
                        'to_status' => $event->to_status,
                        'message' => $event->message,
                        'created_at' => $event->created_at?->toISOString(),
                    ])->values(),
                ]),
        ]);
    }

    public function registerVtu(AddonLifecycleService $lifecycle): RedirectResponse
    {
        $manifest = $this->vtuManifest();
        $addon = Addon::query()->where('identifier', $manifest['identifier'])->first();

        if (!$addon) {
            $lifecycle->register($manifest, auth()->id());

            return back()->with('success', 'VTU & Digital Services addon registered. Install it before activation.');
        }

        if ($addon->status === 'archived') {
            return back()->withErrors([
                'addon' => 'The VTU & Digital Services addon is archived. Use the explicit addon update/restore lifecycle before registering it again.',
            ]);
        }

        return back()->with('success', 'VTU & Digital Services addon is already registered. Install it before activation.');
    }

    public function installVtu(AddonLifecycleService $lifecycle): RedirectResponse
    {
        $manifest = $this->vtuManifest();
        $addon = Addon::query()->where('identifier', $manifest['identifier'])->first();

        if (!$addon) {
            $addon = $lifecycle->register($manifest, auth()->id());
        }

        if ($addon->status === 'archived') {
            return back()->withErrors([
                'addon' => 'The VTU & Digital Services addon is archived. Restore or explicitly update its lifecycle before installation.',
            ]);
        }

        if (in_array($addon->status, ['draft','failed','inactive'], true)) {
            $installed = $lifecycle->install($addon, auth()->id());

            return back()->with('success', $installed->status === 'installed'
                ? 'VTU & Digital Services addon installed. Activate it to expose its services.'
                : 'VTU & Digital Services addon installation did not complete.');
        }

        if ($addon->status === 'installed') {
            return back()->with('success', 'VTU & Digital Services addon is already installed. Activate it to expose its services.');
        }

        if ($addon->status === 'active') {
            return back()->with('success', 'VTU & Digital Services addon is already active.');
        }

        return back()->withErrors([
            'addon' => "VTU & Digital Services addon cannot be installed from its current lifecycle state [{$addon->status}].",
        ]);
    }


    public function registerCac(AddonLifecycleService $lifecycle): RedirectResponse
    {
        $manifest = $this->cacManifest();
        $addon = Addon::query()->where('identifier', $manifest['identifier'])->first();

        if (!$addon) {
            $lifecycle->register($manifest, auth()->id());
            return back()->with('success', 'CAC Services addon registered. Install it before activation.');
        }

        if ($addon->status === 'archived') {
            return back()->withErrors(['addon' => 'The CAC Services addon is archived and cannot be re-registered directly.']);
        }

        return back()->with('success', 'CAC Services addon is already registered.');
    }

    public function installCac(AddonLifecycleService $lifecycle): RedirectResponse
    {
        $manifest = $this->cacManifest();
        $addon = Addon::query()->where('identifier', $manifest['identifier'])->first();

        if (!$addon) {
            $addon = $lifecycle->register($manifest, auth()->id());
        }

        if (in_array($addon->status, ['draft','failed','inactive'], true)) {
            $installed = $lifecycle->install($addon, auth()->id());
            return back()->with('success', $installed->status === 'installed'
                ? 'CAC Services addon installed. Activate it when its provider configuration is ready.'
                : 'CAC Services addon installation did not complete.');
        }

        if (in_array($addon->status, ['installed','active'], true)) {
            return back()->with('success', 'CAC Services addon is already installed or active.');
        }

        return back()->withErrors(['addon' => "CAC Services addon cannot be installed from lifecycle state [{$addon->status}]."]);
    }

    public function register(Request $request, AddonLifecycleService $lifecycle): RedirectResponse
    {
        try { $lifecycle->register($this->validatedManifest($request), $request->user()?->id); return back()->with('success', 'Addon manifest registered.'); }
        catch (\Throwable $e) { report($e); return back()->with('error', 'Addon registration failed safely.'); }
    }

    public function install(Addon $addon, AddonLifecycleService $lifecycle): RedirectResponse
    {
        try { $lifecycle->install($addon, auth()->id()); return back()->with('success', 'Addon installed and left inactive until explicitly enabled.'); }
        catch (\Throwable $e) { report($e); return back()->with('error', 'Addon installation failed safely.'); }
    }

    public function update(Request $request, Addon $addon, AddonLifecycleService $lifecycle): RedirectResponse
    {
        $lifecycle->update($addon, $this->validatedManifest($request), auth()->id());

        return back()->with('success', "Addon updated successfully to the requested version.");
    }

    public function activate(Addon $addon, AddonLifecycleService $lifecycle): RedirectResponse
    {
        try { $lifecycle->activate($addon, auth()->id()); return back()->with('success', 'Addon activated.'); } catch (\Throwable $e) { report($e); return back()->with('error', 'Addon activate failed safely.'); }
    }

    public function disable(Addon $addon, AddonLifecycleService $lifecycle): RedirectResponse
    {
        try { $lifecycle->disable($addon, auth()->id()); return back()->with('success', 'Addon disabled.'); } catch (\Throwable $e) { report($e); return back()->with('error', 'Addon disable failed safely.'); }
    }

    public function uninstall(Addon $addon, AddonLifecycleService $lifecycle): RedirectResponse
    {
        try { $lifecycle->uninstall($addon, auth()->id()); return back()->with('success', 'Addon uninstalled and archived.'); } catch (\Throwable $e) { report($e); return back()->with('error', 'Addon uninstall failed safely.'); }
    }

    public function archive(Addon $addon, AddonLifecycleService $lifecycle): RedirectResponse
    {
        try { $lifecycle->archive($addon, auth()->id()); return back()->with('success', 'Addon archived.'); } catch (\Throwable $e) { report($e); return back()->with('error', 'Addon archive failed safely.'); }
    }

    private function vtuManifest(): array
    {
        return [
            'identifier' => 'vtu.digital-services',
            'name' => 'VTU & Digital Services',
            'version' => '1.0.0',
            'compatibility' => '>=2.0.0',
            'dependencies' => [],
            'permissions' => [
                'vtu.view','vtu.services.manage','vtu.products.manage','vtu.providers.manage',
                'vtu.mappings.manage','vtu.transactions.view','vtu.transactions.manage',
                'vtu.bulk.manage','vtu.requery','vtu.refunds.manage','vtu.settings.manage',
            ],
            'navigation' => [['id' => 'vtu', 'label' => 'VTU', 'url' => '/admin/vtu', 'icon' => 'server', 'permission' => 'vtu.view', 'section' => 'addons', 'order' => 10]],
            'settings' => [],
            'migrations' => [
                '2026_10_05_000026_create_vtu_addon_tables.php',
                '2026_10_05_000027_add_vtu_bulk_idempotency.php',
            ],
            'routes' => ['/vtu'],
            'api_routes' => ['/api/v1/vtu'],
            'services' => ['provider-driven digital services'],
            'provider_integrations' => ['Core ProviderManager'],
            'scheduled_tasks' => ['pending transaction reconciliation'],
            'events' => [],
        ];
    }


    private function cacManifest(): array
    {
        return [
            'identifier' => 'cac.business-services',
            'name' => 'CAC Business Services',
            'version' => '1.0.0',
            'compatibility' => '>=2.0.0',
            'dependencies' => [],
            'permissions' => [
                'cac.view','cac.orders.manage','cac.products.manage','cac.providers.manage',
                'cac.documents.manage','cac.settings.manage','cac.transactions.view',
            ],
            'navigation' => [[
                'id' => 'cac',
                'label' => 'CAC Services',
                'url' => '/admin/cac',
                'icon' => 'briefcase',
                'permission' => 'cac.view',
                'section' => 'addons',
                'order' => 20,
            ]],
            'settings' => [
                ['key' => 'default_review_state', 'type' => 'string', 'default' => 'pending_review'],
                ['key' => 'document_retention_days', 'type' => 'integer', 'default' => 365],
            ],
            'migrations' => [
                '2026_10_06_000100_create_cac_addon_tables.php',
            ],
            'routes' => ['/cac'],
            'api_routes' => ['/api/v1/cac'],
            'services' => [
                'business-name registration',
                'company registration',
                'CAC search and verification',
                'document/order workflow',
            ],
            'provider_integrations' => ['Core ProviderManager'],
            'scheduled_tasks' => ['pending CAC order reconciliation'],
            'events' => ['cac.order.created','cac.order.status.changed','cac.order.completed'],
        ];
    }

    private function validatedManifest(Request $request): array
    {
        return $request->validate([
            'identifier' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:150'],
            'version' => ['required', 'string', 'max:50'],
            'compatibility' => ['nullable', 'string', 'max:100'],
            'dependencies' => ['nullable', 'array'],
            'permissions' => ['nullable', 'array'],
            'navigation' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'migrations' => ['nullable', 'array'],
            'migrations.*' => ['string', 'max:255'],
            'routes' => ['nullable', 'array'],
            'api_routes' => ['nullable', 'array'],
            'menus' => ['nullable', 'array'],
            'widgets' => ['nullable', 'array'],
            'services' => ['nullable', 'array'],
            'provider_integrations' => ['nullable', 'array'],
            'scheduled_tasks' => ['nullable', 'array'],
            'events' => ['nullable', 'array'],
            'checksum' => ['nullable', 'regex:/^[A-Fa-f0-9]{64}$/'],
        ]);
    }
}
