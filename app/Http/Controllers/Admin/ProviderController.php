<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Http\\Controllers\\Controller;
use App\\Models\\ApiProvider;
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
                'priority'=>$p->priority,'credentials'=>$p->maskedCredentials(),
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
            'auth_type'=>'required|string|max:60',
            'credentials'=>'nullable|array',
            'priority'=>'nullable|integer|min:0|max:100000',
        ]);
        $data['credentials']=$data['credentials']??[];
        $data['enabled']=false; $data['paused']=false;
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
            'auth_type'=>'sometimes|required|string|max:60',
            'credentials'=>'nullable|array',
            'priority'=>'nullable|integer|min:0|max:100000',
        ]);
        $provider->fill($data)->save();
        return back()->with('success','Provider updated.');
    }

    public function toggle(ApiProvider $provider): RedirectResponse
    {
        if (!$provider->enabled && ($provider->verification_status !== 'live_verified' || $provider->integration_status !== 'live_verified')) {
            return back()->with('error','Provider must be live-verified before it can be enabled.');
        }
        $provider->update(['enabled'=>!$provider->enabled]);
        return back()->with('success','Provider status updated.');
    }
}
