<?php

namespace Semizzy\Addons\SimHosting\Http\Controllers;

use App\Http\Controllers\Controller;
use Semizzy\Addons\SimHosting\Models\SimHostingNumber;
use Semizzy\Addons\SimHosting\Models\SimHostingProduct;
use Semizzy\Addons\SimHosting\Models\SimHostingRental;

class AdminSimHostingController extends Controller
{
    public function index()
    {
        return inertia('Admin/SimHosting', [
            'products'=>SimHostingProduct::withCount('numbers')->latest()->get(),
            'availableNumbers'=>SimHostingNumber::where('status','available')->count(),
            'rentals'=>SimHostingRental::with(['product','number','user'])->latest()->paginate(25),
        ]);
    }
}