<?php

namespace App\Http\Controllers;

use App\Services\Realtime\RealtimeUpdateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

class RealtimeController extends Controller
{
    public function snapshot(Request $request, RealtimeUpdateService $realtime)
    {
        $snapshot = $realtime->snapshot($request);
        $etag = 'W/"'.sha1(json_encode($snapshot)).'"';
        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)->header('ETag', $etag)->header('Cache-Control', 'no-store');
        }

        return response()->json($snapshot)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('X-Realtime-Mode', 'smart-polling');
    }
}