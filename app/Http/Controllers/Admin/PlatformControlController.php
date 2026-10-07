<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\Audit\AuditLogger;
use App\Services\Platform\TierLimitService;
use App\Services\System\FeatureControlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformControlController extends Controller
{
    public function index(TierLimitService $tiers, FeatureControlService $features): Response
    {
        $keys = collect(range(1,5))->flatMap(fn($t) => ['tier_'.$t.'_daily_limit_minor','tier_'.$t.'_balance_limit_minor'])->all();
        $stored = SystemSetting::query()->whereIn('key',$keys)->pluck('value','key');

        return Inertia::render('Admin/PlatformControls', [
            'features' => array_values($features->all()),
            'tiers' => $tiers->all(),
        ]);
    }

    public function update(Request $request, TierLimitService $tiers, FeatureControlService $features, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'features' => ['nullable','array'],
            'features.*.key' => ['required','string','max:128'],
            'features.*.enabled' => ['required','boolean'],
            'tiers' => ['nullable','array'],
        ]);

        try {
            $changed = [];
            foreach (($data['features'] ?? []) as $item) {
                $before = $features->feature($item['key']);
                $after = $features->setEnabled($item['key'], (bool) $item['enabled']);
                if (($before['enabled'] ?? null) !== $after['enabled']) {
                    $changed[] = ['key'=>$item['key'],'from'=>(bool)($before['enabled'] ?? false),'to'=>(bool)$after['enabled']];
                }
            }

            foreach (range(1,5) as $t) {
                foreach (['daily_limit_minor','balance_limit_minor'] as $field) {
                    $value = $data['tiers'][$t][$field] ?? null;
                    if ($value !== null && preg_match('/^\d+$/', (string)$value)) {
                        SystemSetting::query()->updateOrCreate(
                            ['key'=>"tier_{$t}_{$field}"],
                            ['value'=>(string)$value,'type'=>'string','is_secret'=>false]
                        );
                    }
                }
            }

            $audit->record('admin.global_controls.updated', null, [
                'feature_changes'=>$changed,
                'tier_limits_changed'=>array_keys($data['tiers'] ?? []),
            ], $request);

            return back()->with('success','Global feature controls and tier limits are live.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error',$e->getMessage() ?: 'Global controls could not be saved safely.');
        }
    }
}
