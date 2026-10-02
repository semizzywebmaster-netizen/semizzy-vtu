<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

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
        DB::transaction(function () use ($addon): void {
            $from=$addon->status;
            $addon->update(['status'=>'active','activated_at'=>now(),'last_error'=>null]);
            $addon->lifecycleEvents()->create([
                'addon_identifier'=>$addon->identifier,'event'=>'activated','from_status'=>$from,'to_status'=>'active',
                'message'=>'Addon activated by administrator.','actor_id'=>auth()->id(),
            ]);
        });
        return back()->with('success','Addon activated.');
    }

    public function disable(Addon $addon): RedirectResponse
    {
        abort_unless($addon->canTransitionTo('inactive'), 409, 'Addon cannot transition to inactive from its current state.');
        DB::transaction(function () use ($addon): void {
            $from=$addon->status;
            $addon->update(['status'=>'inactive']);
            $addon->lifecycleEvents()->create([
                'addon_identifier'=>$addon->identifier,'event'=>'disabled','from_status'=>$from,'to_status'=>'inactive',
                'message'=>'Addon disabled by administrator.','actor_id'=>auth()->id(),
            ]);
        });
        return back()->with('success','Addon disabled.');
    }
}
