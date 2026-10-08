<?php

namespace App\Http\Controllers;

use App\Models\VtuConversionRequest;
use App\Services\Vtu\VtuConversionService;
use App\Services\Vtu\VtuServiceRegistry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VtuConversionController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('VTU/Conversions', [
            'conversionTypes'=>VtuConversionService::TYPES,
            'requests'=>VtuConversionRequest::query()->where('user_id',$request->user()->id)->latest()->paginate(20),
            'conversionSettings'=>app(VtuServiceRegistry::class)->conversionSettings(),
        ]);
    }

    public function store(Request $request, VtuConversionService $service)
    {
        $data=$request->validate([
            'conversion_type'=>['required','string','in:airtime_to_cash,airtime_to_data,data_to_cash,data_to_airtime'],
            'network'=>['required','string','max:30'],
            'source_amount'=>['required','numeric','min:1'],
            'source_phone'=>['nullable','string','max:30'],
            'source_product'=>['nullable','string','max:160'],
            'quantity'=>['nullable','integer','min:1','max:1000'],
            'target_phone'=>['nullable','string','max:30'],
            'target_product'=>['nullable','string','max:160'],
            'data_plan'=>['nullable','string','max:160'],
            'proof'=>['nullable','file','max:5120','mimes:jpg,jpeg,png,pdf'],
        ]);

        $requestModel=$service->create($request->user()->id,$data,$request->file('proof'));

        return response()->json(['data'=>$requestModel],201);
    }

    public function show(Request $request, VtuConversionRequest $conversion)
    {
        abort_unless((int)$conversion->user_id===(int)$request->user()->id,404);
        return response()->json(['data'=>$conversion]);
    }
}