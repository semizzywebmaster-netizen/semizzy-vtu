<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Services\Addons\AddonLifecycleService;
use App\Services\Addons\AddonRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AddonController extends Controller
{
    public function index(AddonRegistry $registry): Response
    {
        return Inertia::render('Admin/Addons', [
            'availableAddons' => collect($registry->all())->map(fn (array $manifest) => [
                'identifier' => $manifest['identifier'],
                'name' => $manifest['name'],
                'version' => $manifest['version'],
                'compatibility' => $manifest['compatibility'] ?? null,
                'dependencies' => $manifest['dependencies'] ?? [],
                'permissions' => $manifest['permissions'] ?? [],
            ])->values(),
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

    public function register(Request $request, AddonRegistry $registry, AddonLifecycleService $lifecycle): RedirectResponse
    {
        try {
            $data = $request->validate(['identifier' => ['required','string','max:100']]);
            $manifest = $registry->require($data['identifier']);
            unset($manifest['source']);
            $lifecycle->register($manifest, $request->user()?->id);

            return back()->with('success', 'Addon manifest registered.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Addon registration failed safely.');
        }
    }

    public function install(Addon $addon, AddonLifecycleService $lifecycle): RedirectResponse
    {
        try {
            $lifecycle->install($addon, auth()->id());
            return back()->with('success', 'Addon installed and left inactive until explicitly enabled.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Addon installation failed safely.');
        }
    }

    public function update(Request $request, Addon $addon, AddonRegistry $registry, AddonLifecycleService $lifecycle): RedirectResponse
    {
        try {
            $data = $request->validate(['identifier' => ['required','string','max:100']]);
            if (strcasecmp($data['identifier'], $addon->identifier) !== 0) {
                throw new \InvalidArgumentException('Addon identifier does not match the selected addon.');
            }

            $manifest = $registry->require($addon->identifier);
            unset($manifest['source']);
            $lifecycle->update($addon, $manifest, auth()->id());

            return back()->with('success', 'Addon updated successfully from its installed registry manifest.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Addon update failed safely.');
        }
    }

    public function activate(Addon $addon, AddonLifecycleService $lifecycle): RedirectResponse
    {
        try {
            $lifecycle->activate($addon, auth()->id());
            return back()->with('success', 'Addon activated.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Addon activate failed safely.');
        }
    }

    public function disable(Addon $addon, AddonLifecycleService $lifecycle): RedirectResponse
    {
        try {
            $lifecycle->disable($addon, auth()->id());
            return back()->with('success', 'Addon disabled.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Addon disable failed safely.');
        }
    }

    public function uninstall(Addon $addon, AddonLifecycleService $lifecycle): RedirectResponse
    {
        try {
            $lifecycle->uninstall($addon, auth()->id());
            return back()->with('success', 'Addon uninstalled and archived.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Addon uninstall failed safely.');
        }
    }

    public function archive(Addon $addon, AddonLifecycleService $lifecycle): RedirectResponse
    {
        try {
            $lifecycle->archive($addon, auth()->id());
            return back()->with('success', 'Addon archived.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Addon archive failed safely.');
        }
    }
}
