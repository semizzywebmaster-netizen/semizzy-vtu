<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\Audit\AuditLogger;
use App\Services\Platform\TierLimitService;
use App\Models\Service;
use App\Models\Addon;
use App\Services\Addons\AddonLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformControlController extends Controller
{
    private const FEATURES=['registration_enabled','kyc_enabled','finance_enabled','vtu_enabled','support_enabled','notifications_enabled','api_enabled','maintenance_mode'];

    public function index(TierLimitService $tiers): Response
    {
        $keys=array_merge(self::FEATURES,['registration_verification_enabled','registration_otp_channels','registration_otp_expiry_minutes','registration_otp_max_attempts','registration_otp_resend_seconds'],collect(range(1,5))->flatMap(fn($t)=>['tier_'.$t.'_daily_limit_minor','tier_'.$t.'_balance_limit_minor'])->all());
        $stored=SystemSetting::query()->whereIn('key',$keys)->pluck('value','key');
        $features=[];
        foreach(self::FEATURES as $key) $features[$key]=filter_var($stored->get($key, $key==='maintenance_mode'?'0':'1'),FILTER_VALIDATE_BOOL);
                $registration = [
            'enabled' => filter_var($stored->get('registration_verification_enabled', '1'), FILTER_VALIDATE_BOOL),
            'channels' => json_decode((string) $stored->get('registration_otp_channels', '["email"]'), true) ?: ['email'],
            'expiry_minutes' => (int) $stored->get('registration_otp_expiry_minutes', 10),
            'max_attempts' => (int) $stored->get('registration_otp_max_attempts', 5),
            'resend_seconds' => (int) $stored->get('registration_otp_resend_seconds', 60),
        ];
        return Inertia::render('Admin/PlatformControls',['tiers'=>$tiers->all(),'features'=>$features,'registrationVerification'=>$registration,'services'=>Service::query()->with('category')->orderBy('name')->get(['id','category_id','key','name','description','enabled','metadata'])->map(fn(Service $s)=>['id'=>$s->id,'key'=>$s->key,'name'=>$s->name,'description'=>$s->description,'enabled'=>(bool)$s->enabled,'category'=>$s->category?->name])->values(),'addons'=>Addon::query()->orderBy('name')->get(['id','identifier','name','version','status'])->map(fn(Addon $a)=>['id'=>$a->id,'identifier'=>$a->identifier,'name'=>$a->name,'version'=>$a->version,'status'=>$a->status,'enabled'=>$a->status==='active'])->values()]);
    }

    public function updateRegistrationVerification(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => 'required|boolean',
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:email,sms,whatsapp',
            'expiry_minutes' => 'required|integer|in:5,10,15,30,60',
            'max_attempts' => 'required|integer|in:3,5,6,8,10',
            'resend_seconds' => 'required|integer|in:30,60,90,120,300',
        ]);
        SystemSetting::query()->updateOrCreate(['key'=>'registration_verification_enabled'],['value'=>$data['enabled']?'1':'0','type'=>'boolean','is_secret'=>false]);
        SystemSetting::query()->updateOrCreate(['key'=>'registration_otp_channels'],['value'=>json_encode(array_values(array_unique($data['channels']))),'type'=>'json','is_secret'=>false]);
        foreach (['expiry_minutes','max_attempts','resend_seconds'] as $key) {
            SystemSetting::query()->updateOrCreate(['key'=>'registration_otp_'.$key],['value'=>(string)$data[$key],'type'=>'integer','is_secret'=>false]);
        }
        try {$audit->record('admin.registration.verification_controls.updated', null, $data, $request);} catch (\Throwable $e) { report($e); }
        return back()->with('success','Registration verification controls saved.');
    }

    public function toggleService(Request $request, Service $service, AuditLogger $audit): RedirectResponse
    {
        $data=$request->validate(['enabled'=>'required|boolean']);
        $before=(bool)$service->enabled; $after=(bool)$data['enabled'];
        $service->updateOrFail(['enabled'=>$after]);
        $audit->record('admin.platform.service_toggled',$service,['service_id'=>$service->id,'key'=>$service->key,'from'=>$before,'to'=>$after],$request);
        return back()->with('success',($after?'Enabled ':'Disabled ').$service->name.'.');
    }

    public function toggleAddon(Request $request, Addon $addon, AddonLifecycleService $lifecycle, AuditLogger $audit): RedirectResponse
    {
        $data=$request->validate(['enabled'=>'required|boolean']);
        $want=(bool)$data['enabled'];
        try {
            if($want && $addon->status!=='active') $lifecycle->activate($addon,auth()->id());
            if(!$want && $addon->status==='active') $lifecycle->disable($addon,auth()->id());
            $audit->record('admin.platform.addon_toggled',$addon,['addon_id'=>$addon->id,'identifier'=>$addon->identifier,'enabled'=>$want],$request);
            return back()->with('success',($want?'Enabled ':'Disabled ').$addon->name.'.');
        } catch (\Throwable $e) { report($e); return back()->with('error','Addon state could not be changed safely.'); }
    }

    public function update(Request $request, TierLimitService $tiers, AuditLogger $audit): RedirectResponse
    {
        $rules=['features'=>'nullable|array'];
        foreach(self::FEATURES as $key) $rules['features.'.$key]='boolean';
        foreach(range(1,5) as $t){
            $rules["tiers.$t.daily_limit_minor"]=['nullable','string','regex:/^\d+$/'];
            $rules["tiers.$t.balance_limit_minor"]=['nullable','string','regex:/^\d+$/'];
        }
        $data=$request->validate($rules);
        try{
            foreach(self::FEATURES as $key) SystemSetting::query()->updateOrCreate(['key'=>$key],['value'=>!empty($data['features'][$key])?'1':'0','type'=>'boolean','is_secret'=>false]);
            foreach(range(1,5) as $t){
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
