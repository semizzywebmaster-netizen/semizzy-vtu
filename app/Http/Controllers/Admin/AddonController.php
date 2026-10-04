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

    public function installVtu(AddonLifecycleService $lifecycle): RedirectResponse
    {
        $manifest = [
            'identifier' => 'vtu.digital-services',
            'name' => 'VTU & Digital Services',
            'version' => '1.0.0',
            'compatibility' => '>=2.0.0',
            'dependencies' => [],
            'permissions' => ['vtu.view','vtu.services.manage','vtu.products.manage','vtu.providers.manage','vtu.mappings.manage','vtu.transactions.view','vtu.transactions.manage','vtu.bulk.manage','vtu.requery','vtu.refunds.manage','vtu.settings.manage'],
            'navigation' => [['label' => 'VTU', 'url' => '/vtu', 'permission' => 'vtu.view']],
            'settings' => [],
            'migrations' => ['2026_10_05_000026_create_vtu_addon_tables.php'],
            'routes' => ['/vtu'],
            'api_routes' => ['/api/v1/vtu'],
            'services' => ['provider-driven digital services'],
            'provider_integrations' => ['Core ProviderManager'],
            'scheduled_tasks' => ['pending transaction reconciliation'],
            'events' => [],
        ];
        $addon = Addon::query()->where('identifier', $manifest['identifier'])->first();
        if (!$addon) $addon = $lifecycle->register($manifest, auth()->id());
        if (in_array($addon->status, ['draft','failed','inactive'], true)) $lifecycle->install($addon, auth()->id());
        return back()->with('success', 'VTU & Digital Services addon installed. Activate it to expose its services.');
    }

    public function register(Request $request, AddonLifecycleService $lifecycle): RedirectResponse
    {
        $lifecycle->register($this->validatedManifest($request), $request->user()?->id);

        return back()->with('success', 'Addon manifest registered.');
    }

    public function install(Addon $addon, AddonLifecycleService $lifecycle): RedirectResponse
    {
        $lifecycle->install($addon, auth()->id());

        return back()->with('success', 'Addon installed and left inactive until explicitly enabled.');
    }

    public function update(Request $request, Addon $addon, AddonLifecycleService $lifecycle): RedirectResponse
    {
        $lifecycle->update($addon, $this->validatedManifest($request), auth()->id());

        return back()->with('success', "Addon updated successfully to the requested version.");
    }

    public function activate(Addon $addon, AddonLifecycleService $lifecycle): RedirectResponse
    {
        $lifecycle->activate($addon, auth()->id());

        return back()->with('success', 'Addon activated.');
    }

    public function disable(Addon $addon, AddonLifecycleService $lifecycle): RedirectResponse
    {
        $lifecycle->disable($addon, auth()->id());

        return back()->with('success', 'Addon disabled.');
    }

    public function uninstall(Addon $addon, AddonLifecycleService $lifecycle): RedirectResponse
    {
        $lifecycle->uninstall($addon, auth()->id());

        return back()->with('success', 'Addon uninstalled and archived.');
    }

    public function archive(Addon $addon, AddonLifecycleService $lifecycle): RedirectResponse
    {
        $lifecycle->archive($addon, auth()->id());

        return back()->with('success', 'Addon archived.');
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
