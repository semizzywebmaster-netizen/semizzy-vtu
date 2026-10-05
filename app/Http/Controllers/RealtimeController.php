<?php

namespace App\Http\Controllers;

use App\Services\Realtime\RealtimeUpdateService;
use Illuminate\Http\Request;

class RealtimeController extends Controller
{
    public function snapshot(Request $request, RealtimeUpdateService $realtime)
    {
        return response()->json($realtime->snapshot($request))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('X-Realtime-Mode', 'smart-polling');
    }
}