<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\SystemSetting;
use App\Services\Audit\AuditLogger;
use App\Services\Platform\FeatureRolloutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeatureRolloutController extends Controller
{
    public function index(FeatureRolloutService $rollouts): Response
    {
        $saved = $rollouts->all();
        $addons = Addon::query()
            ->whereNotIn('status', ['archived'])
            ->orderBy('name')
            ->get(['identifier', 'name', 'version', 'status'])
            ->map(function (Addon $addon) use ($saved): array {
                $flag = $saved[$addon->identifier] ?? [];
                return [
                    'identifier' => $addon->identifier,
                    'name' => $addon->name,
                    'version' => $addon->version,
                    'status' => $addon->status,
                    'enabled' => (bool) ($flag['enabled'] ?? true),
                    'percentage' => max(0, min(100, (int) ($flag['percentage'] ?? 100))),
                ];
            })->values();

        return Inertia::render('Admin/FeatureRollouts', ['addons' => $addons]);
    }

    public function update(Request $request, FeatureRolloutService $rollouts, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'rollouts' => ['required', 'array', 'max:100'],
            'rollouts.*.identifier' => ['required', 'string', 'max:160'],
            'rollouts.*.enabled' => ['required', 'boolean'],
            'rollouts.*.percentage' => ['required', 'integer', 'between:0,100'],
        ]);

        $allowed = Addon::query()->whereNotIn('status', ['archived'])->pluck('identifier')->all();
        $current = $rollouts->all();
        $changed = [];

        foreach ($data['rollouts'] as $row) {
            $key = (string) $row['identifier'];
            if (! in_array($key, $allowed, true)) {
                continue;
            }
            $next = [
                'enabled' => (bool) $row['enabled'],
                'percentage' => (int) $row['percentage'],
            ];
            if (($current[$key] ?? ['enabled' => true, 'percentage' => 100]) !== $next) {
                $changed[$key] = ['before' => $current[$key] ?? ['enabled' => true, 'percentage' => 100], 'after' => $next];
            }
            $current[$key] = $next;
        }

        SystemSetting::query()->updateOrCreate(
            ['key' => 'feature_rollouts'],
            ['value' => json_encode($current, JSON_UNESCAPED_SLASHES), 'type' => 'json', 'is_secret' => false],
        );

        $audit->record('admin.feature_rollouts.updated', null, ['changes' => $changed], $request);
        return back()->with('success', 'Feature rollout settings saved. Percentage rollout is deterministic per user; administrators retain access for verification and recovery.');
    }
}
