<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Http\\Controllers\\Controller;
use App\\Models\\ApiProvider;
use App\\Services\\Providers\\ProviderTestService;
use App\\Services\\Providers\\ProviderUrlGuard;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Inertia\\Inertia;
use Inertia\\Response;

class ProviderController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Providers', [
            'providers' => ApiProvider::query()->latest()->get()->map(fn (ApiProvider $p) => [
                'id'=>$p->id,'identifier'=>$p->identifier,'display_name'=>$p->display_name,
                'environment'=>$p->environment,'verification_status'=>$p->verification_status,
                'integration_status'=>$p->integration_status,'enabled'=>$p->enabled,'paused'=>$p->paused,
                'priority'=>$p->priority,'credentials'=>$p->maskedCredentials(),'capabilities'=>$p->capabilities??[],'endpoints'=>$p->endpoints??[],
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data=$request->validate([
            'identifier'=>'required|string|max:100|alpha_dash|unique:api_providers,identifier',
            'display_name'=>'required|string|max:160',
            'base_url'=>'nullable|url:http,https|max:500',
            'documentation_url'=>'nullable|url:http,https|max:500',
            'official_website'=>'nullable|url:http,https|max:500',
            'environment'=>'required|in:sandbox,production',
            'auth_type'=>'required|in:custom,bearer,basic,api_key_header',
            'capabilities'=>'nullable|array','endpoints'=>'nullable|array','service_categories'=>'nullable|array',
            'credentials'=>'nullable|array',
            'priority'=>'nullable|integer|min:0|max:100000',
        ]);
        app(ProviderUrlGuard::class)->validate($data['base_url'] ?? null);
        $data['credentials']=$data['credentials']??[];
        $data['enabled']=false; $data['paused']=true;
        $data['verification_status']='unverified'; $data['integration_status']='draft';
        ApiProvider::create($data);
        return back()->with('success','Provider saved as unverified and disabled.');
    }

    public function update(Request $request, ApiProvider $provider): RedirectResponse
    {
        $data=$request->validate([
            'display_name'=>'sometimes|required|string|max:160',
            'base_url'=>'nullable|url:http,https|max:500',
            'documentation_url'=>'nullable|url:http,https|max:500',
            'official_website'=>'nullable|url:http,https|max:500',
            'environment'=>'sometimes|required|in:sandbox,production',
            'auth_type'=>'sometimes|required|in:custom,bearer,basic,api_key_header',
            'capabilities'=>'nullable|array','endpoints'=>'nullable|array','service_categories'=>'nullable|array',
            'credentials'=>'nullable|array',
            'priority'=>'nullable|integer|min:0|max:100000',
        ]);
        app(ProviderUrlGuard::class)->validate($data['base_url'] ?? $provider->base_url);
        $provider->fill($data)->save();
        return back()->with('success','Provider updated.');
    }

    public function test(ApiProvider $provider, ProviderTestService $tester): RedirectResponse
    {
        try {
            $result=$tester->test($provider);
        } catch (\\Throwable $e) {
            $provider->forceFill([
                'last_tested_at'=>now(),
                'last_test_status'=>'FAILED',
                'last_test_summary'=>'Provider test failed safely: '.mb_substr($e->getMessage(),0,500),
                'enabled'=>false,
                'paused'=>true,
            ])->save();
            return back()->with('error','Provider test failed safely.');
        }

        if ($result['result']->accepted) {
            $verified=$provider->environment==='production';
            $provider->update([
                'verification_status'=>$verified?'live_verified':'sandbox_verified',
                'integration_status'=>$verified?'live_verified':'sandbox_verified',
                'enabled'=>false,
                'paused'=>true,
            ]);
            return back()->with('success','Provider health check succeeded. Provider remains disabled until explicitly enabled.');
        }

        $provider->update(['enabled'=>false,'paused'=>true,'verification_status'=>'test_failed']);
        return back()->with('error','Provider test did not succeed: '.($result['result']->message??$result['result']->status));
    }

    public function toggle(ApiProvider $provider): RedirectResponse
    {
        if (!$provider->enabled && ($provider->verification_status !== 'live_verified' || $provider->integration_status !== 'live_verified')) {
            return back()->with('error','Provider must be live-verified before it can be enabled.');
        }
        $enabled=!$provider->enabled;
        $provider->update(['enabled'=>$enabled,'paused'=>!$enabled]);
        return back()->with('success','Provider status updated.');
    }
}
