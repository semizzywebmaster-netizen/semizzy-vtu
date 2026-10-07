<?php
namespace Addons\CommunicationWhatsapp\Http\Controllers;
use Addons\CommunicationWhatsapp\Services\WhatsAppStatusService;
use App\Models\Communication\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;
class WhatsAppStatusController {
 public function receive(Request $request,Provider $provider,WhatsAppStatusService $service): Response {
  try { $service->handle($provider,$request->json()->all()); return response('OK',200); }
  catch(Throwable $e) { return response('Status rejected',400); }
 }
}