<?php

namespace App\Http\Controllers\Admin;

use App\Models\VtuConversionRequest;
use App\Services\Vtu\VtuConversionService;
use App\Services\Vtu\VtuServiceRegistry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VtuConversionAdminController extends \Illuminate\Routing\Controller
{
    public function index()
    {
        return Inertia::render('Admin/VTU/Conversions', [
            'requests'=>VtuConversionRequest::query()->with(['user','operator'])->latest()->paginate(30),
            'conversionTypes'=>VtuConversionService::TYPES,
            'conversionSettings'=>app(VtuServiceRegistry::class)->conversionSettings(),
        ]);
    }

    public function saveSettings(Request $request, VtuServiceRegistry $registry)
    {
        $data=$request->validate(['settings'=>'required|array','settings.airtime_to_cash.rate_percent'=>'required|numeric|gt:0|lte:100','settings.airtime_to_data.rate_percent'=>'required|numeric|gt:0|lte:100','settings.data_to_cash.rate_percent'=>'required|numeric|gt:0|lte:100','settings.data_to_airtime.rate_percent'=>'required|numeric|gt:0|lte:100','settings.airtime_to_cash.fee'=>'nullable|numeric|min:0','settings.airtime_to_data.fee'=>'nullable|numeric|min:0','settings.data_to_cash.fee'=>'nullable|numeric|min:0','settings.data_to_airtime.fee'=>'nullable|numeric|min:0']);
        $registry->saveConversionSettings($data['settings']);
        return response()->json(['status'=>'completed','settings'=>$registry->conversionSettings()]);
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