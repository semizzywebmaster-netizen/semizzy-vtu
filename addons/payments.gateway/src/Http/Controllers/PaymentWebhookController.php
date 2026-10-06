<?php

namespace Semizzy\Addons\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use Illuminate\Http\Request;
use Semizzy\Addons\Payments\Services\PaymentWebhookService;

final class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, PaymentWebhookService $service)
    {
        $apiProvider = ApiProvider::query()->where('identifier',$provider)->firstOrFail();
        $event = $service->handle($apiProvider, $request->all(), collect($request->headers->all())->map(fn($v)=>is_array($v)?($v[0]??''):$v)->all());
        return response()->json(['ok'=>true,'event_id'=>$event->event_id,'status'=>$event->processing_status]);
    }
}