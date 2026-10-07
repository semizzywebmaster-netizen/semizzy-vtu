<?php
namespace Addons\CommunicationWhatsapp\Http\Controllers;

use Addons\CommunicationWhatsapp\Services\WhatsAppDeliveryStatusService;
use Addons\CommunicationWhatsapp\Services\WhatsAppWebhookService;
use App\Models\Communication\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class WhatsAppWebhookController
{
 public function verify(Request $request,Provider $provider,WhatsAppWebhookService $service): Response
 {
  try {
   return response($service->verify($provider,$request->query('hub_mode'),$request->query('hub_verify_token'),$request->query('hub_challenge')),200);
  } catch(Throwable $e) {
   return response('Forbidden',403);
  }
 }

 public function receive(Request $request,Provider $provider,WhatsAppWebhookService $service,WhatsAppDeliveryStatusService $statusService): Response
 {
  try {
   $raw=$request->getContent();
   $service->handle($provider,$raw,$request->header('X-Hub-Signature-256'));
   $payload=json_decode($raw,true);
   if(is_array($payload)) $statusService->handle($payload);
   return response('OK',200);
  } catch(Throwable $e) {
   return response('Webhook rejected',400);
  }
 }
}
