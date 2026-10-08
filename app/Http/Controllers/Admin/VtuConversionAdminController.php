<?php

namespace App\Http\Controllers\Admin;

use App\Models\VtuConversionRequest;
use App\Services\Vtu\VtuConversionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VtuConversionAdminController extends \Illuminate\Routing\Controller
{
    public function index()
    {
        return Inertia::render('Admin/VTU/Conversions', [
            'requests'=>VtuConversionRequest::query()->with(['user','operator'])->latest()->paginate(30),
            'conversionTypes'=>VtuConversionService::TYPES,
        ]);
    }

    public function verify(Request $request, VtuConversionRequest $conversion, VtuConversionService $service)
    {
        $data=$request->validate(['receiving_account'=>['required','string','max:100'],'note'=>['nullable','string','max:1000']]);
        return response()->json(['data'=>$service->verify($conversion,(int)$request->user()->id,$data['receiving_account'],$data['note']??null)]);
    }

    public function approve(Request $request, VtuConversionRequest $conversion, VtuConversionService $service)
    {
        $data=$request->validate(['note'=>['nullable','string','max:1000']]);
        return response()->json(['data'=>$service->approve($conversion,(int)$request->user()->id,$data['note']??null)]);
    }

    public function reject(Request $request, VtuConversionRequest $conversion, VtuConversionService $service)
    {
        $data=$request->validate(['reason'=>['required','string','max:1000']]);
        return response()->json(['data'=>$service->reject($conversion,(int)$request->user()->id,$data['reason'])]);
    }
}
