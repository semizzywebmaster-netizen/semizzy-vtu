<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\Audit\AuditLogger;
use App\Services\Platform\TierLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformControlController extends Controller
{
    private const FEATURES=['registration_enabled','kyc_enabled','finance_enabled','vtu_enabled','support_enabled','notifications_enabled','api_enabled','maintenance_mode'];

    public function index(TierLimitService $tiers): Response
    {
        $keys=array_merge(self::FEATURES,collect(range(1,4))->flatMap(fn($t)=>['tier_'.$t.'_daily_limit_minor','tier_'.$t.'_balance_limit_minor'])->all());
        $stored=SystemSetting::query()->whereIn('key',$keys)->pluck('value','key');
        $features=[];
        foreach(self::FEATURES as $key) $features[$key]=filter_var($stored->get($key, $key==='maintenance_mode'?'0':'1'),FILTER_VALIDATE_BOOL);
        return Inertia::render('Admin/PlatformControls',['tiers'=>$tiers->all(),'features'=>$features]);
    }

    public function update(Request $request, TierLimitService $tiers, AuditLogger $audit): RedirectResponse
    {
        $rules=['features'=>'nullable|array'];
        foreach(self::FEATURES as $key) $rules['features.'.$key]='boolean';
        foreach(range(1,4) as $t){
            $rules["tiers.$t.daily_limit_minor"]=['nullable','string','regex:/^\d+$/'];
            $rules["tiers.$t.balance_limit_minor"]=['nullable','string','regex:/^\d+$/'];
        }
        $data=$request->validate($rules);
        try{
            foreach(self::FEATURES as $key) SystemSetting::query()->updateOrCreate(['key'=>$key],['value'=>!empty($data['features'][$key])?'1':'0','type'=>'boolean','is_secret'=>false]);
            foreach(range(1,4) as $t){
                foreach(['daily_limit_minor','balance_limit_minor'] as $field){
                    $value=$data['tiers'][$t][$field]??null;
                    if($value!==null) SystemSetting::query()->updateOrCreate(['key'=>"tier_{$t}_{$field}"],['value'=>$value,'type'=>'string','is_secret'=>false]);
                }
            }
            try{$audit->record('admin.platform.controls.updated',null,['feature_keys'=>self::FEATURES,'tier_limits_changed'=>array_keys($data['tiers']??[])],$request);}catch(\Throwable $e){report($e);}
            return back()->with('success','Platform controls and tier limits saved.');
        }catch(\Throwable $e){report($e);return back()->with('error','Platform controls could not be saved safely.');}
    }
}
