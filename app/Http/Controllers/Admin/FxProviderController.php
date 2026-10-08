<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FxRateProvider;
use App\Services\Fx\FxRateService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FxProviderController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/FxProviders', [
            'providers' => FxRateProvider::query()->orderBy('priority')->get()->map(function (FxRateProvider $p) {
                return [
                    'id'=>$p->id,'name'=>$p->name,'code'=>$p->code,'driver'=>$p->driver,
                    'base_url'=>$p->base_url,'priority'=>$p->priority,'weight'=>$p->weight,
                    'enabled'=>$p->enabled,'paused'=>$p->paused,'maintenance'=>$p->maintenance,
                    'has_credentials'=>!empty($p->credentials),
                    'failure_count'=>$p->failure_count,'last_success_at'=>$p->last_success_at,
                    'last_error'=>$p->last_error,
                ];
            }),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'=>['required','string','max:120'],
            'code'=>['required','string','max:80','regex:/^[a-z0-9_-]+$/','unique:fx_rate_providers,code'],
            'driver'=>['required','string','max:80'],
            'base_url'=>['nullable','url','max:500'],
            'credentials'=>['nullable','array'],
            'settings'=>['nullable','array'],
            'priority'=>['required','integer','min:1','max:9999'],
            'weight'=>['required','integer','min:1','max:100'],
            'enabled'=>['boolean'],
        ]);
        FxRateProvider::create($data);
        return back()->with('success','FX provider added.');
    }

    public function update(Request $request, FxRateProvider $provider)
    {
        $data = $request->validate([
            'name'=>['required','string','max:120'],
            'base_url'=>['nullable','url','max:500'],
            'credentials'=>['nullable','array'],
            'settings'=>['nullable','array'],
            'priority'=>['required','integer','min:1','max:9999'],
            'weight'=>['required','integer','min:1','max:100'],
            'enabled'=>['boolean'],'paused'=>['boolean'],'maintenance'=>['boolean'],
        ]);
        if (array_key_exists('credentials',$data) && $data['credentials'] === null) unset($data['credentials']);
        $provider->update($data);
        return back()->with('success','FX provider updated.');
    }

    public function toggle(FxRateProvider $provider)
    {
        $provider->update(['enabled'=>!$provider->enabled]);
        return back()->with('success', $provider->enabled ? 'FX provider enabled.' : 'FX provider disabled.');
    }

    public function test(Request $request, FxRateProvider $provider, FxRateService $fx)
    {
        $data = $request->validate(['from'=>['required','string','size:3'],'to'=>['required','string','size:3']]);
        if (!$provider->enabled) $provider->update(['enabled'=>true]);
        try {
            $result = $fx->rate(strtoupper($data['from']), strtoupper($data['to']));
            return back()->with('success','FX test passed: '.$result['rate'].' '.$data['to'].' per '.$data['from'].'.');
        } catch (\Throwable) {
            return back()->with('error','FX provider test failed. Check its endpoint, credentials and response mapping.');
        }
    }
}