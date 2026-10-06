<?php
namespace AppHttpControllersApi;
use AppHttpControllersController;
use AppModelsApiProvider;
use AppServicesCacCacWebhookService;
use IlluminateHttpRequest;
class CacWebhookController extends Controller {
 public function handle(Request $request, ApiProvider $provider, CacWebhookService $service) {
  try { return response()->json(['ok'=>true,'data'=>$service->handle($request,$provider)]); }
  catch (\Throwable $e) { return response()->json(['ok'=>false,'message'=>$e->getMessage()], in_array($e->getMessage(),['Invalid webhook signature.','Webhook signature is not configured.'],true)?401:422); }
 }
}