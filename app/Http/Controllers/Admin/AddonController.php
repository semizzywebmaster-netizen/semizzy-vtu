<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Http\\Controllers\\Controller;
use App\\Models\\Addon;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Inertia\\Inertia;
use Inertia\\Response;

class AddonController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Addons', [
            'addons'=>Addon::query()->latest()->get()->map(fn(Addon $a)=>[
                'id'=>$a->id,'identifier'=>$a->identifier,'name'=>$a->name,'version'=>$a->version,
                'status'=>$a->status,'last_error'=>$a->last_error,
            ]),
        ]);
    }

    public function activate(Addon $addon): RedirectResponse
    {
        abort_unless($addon->canTransitionTo('active'), 409, 'Addon cannot transition to active from its current state.');
        $addon->update(['status'=>'active','activated_at'=>now(),'last_error'=>null]);
        return back()->with('success','Addon activated.');
    }

    public function disable(Addon $addon): RedirectResponse
    {
        abort_unless($addon->canTransitionTo('inactive'), 409, 'Addon cannot transition to inactive from its current state.');
        $addon->update(['status'=>'inactive']);
        return back()->with('success','Addon disabled.');
    }
}
