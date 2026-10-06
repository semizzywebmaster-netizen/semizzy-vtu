<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CacServiceProduct;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CacServiceProductController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Cac/Products', [
            'products' => CacServiceProduct::query()->withCount('providerRoutes')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'identifier'=>['required','string','max:100','regex:/^[a-z0-9._-]+$/'],
            'name'=>['required','string','max:150'],
            'service_type'=>['required','string','max:80'],
            'description'=>['nullable','string','max:2000'],
            'currency'=>['required','string','size:3'],
            'provider_price_minor'=>['nullable','integer','min:0'],
            'selling_price_minor'=>['nullable','integer','min:0'],
            'enabled'=>['sometimes','boolean'],
            'requirements'=>['nullable','array'],
        ]);

        CacServiceProduct::create($data);

        return back()->with('success','CAC service product created.');
    }

    public function update(Request $request, CacServiceProduct $product): RedirectResponse
    {
        $data = $request->validate([
            'name'=>['required','string','max:150'],
            'service_type'=>['required','string','max:80'],
            'description'=>['nullable','string','max:2000'],
            'currency'=>['required','string','size:3'],
            'provider_price_minor'=>['nullable','integer','min:0'],
            'selling_price_minor'=>['nullable','integer','min:0'],
            'enabled'=>['sometimes','boolean'],
            'requirements'=>['nullable','array'],
        ]);

        $product->update($data);

        return back()->with('success','CAC service product updated.');
    }
}
