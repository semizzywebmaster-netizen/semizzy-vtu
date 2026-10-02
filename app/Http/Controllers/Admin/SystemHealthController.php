<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\System\SystemHealthService;
use Inertia\Inertia;
use Inertia\Response;

class SystemHealthController extends Controller
{
    public function __invoke(SystemHealthService $health): Response
    {
        return Inertia::render('Admin/SystemHealth', [
            'health' => $health->check(),
        ]);
    }
}
