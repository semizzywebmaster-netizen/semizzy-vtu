<?php
namespace AppServicesCac;
use AppModelsApiProvider;
use AppModelsCacOrder;
use AppModelsCacWebhookEvent;
use IlluminateHttpRequest;
use IlluminateSupportFacadesDB;
use RuntimeException;
class CacWebhookService {
 public function handle(Request $request, ApiProvider $provider): array {
  $raw=$request->getContent();
  $credentials=(array)($provider->credentials??[]);
  $secret=$credentials['webhook_secret']??$credentials['webhookSecret']??null;
  $signature=$request->header('X-Webhook-Signature')??$request->header('X-Signature');
  if (!$secret || !$signature) throw new RuntimeException('Webhook signature is not configured.');
  $provided=trim($signature);
  if (str_starts_with($provided,'sha256=')) $provided=substr($provided,7);
  $expected=hash_hmac('sha256',$raw,(string)$secret);
  if (!hash_equals($expected,$provided)) throw new RuntimeException('Invalid webhook signature.');
  $payload=$request->json()->all();
  if (!is_array($payload)) throw new RuntimeException('Invalid webhook payload.');
  $eventId=(string)($request->header('X-Webhook-Id')??$payload['event_id']??$payload['id']??'');
  if ($eventId==='') throw new RuntimeException('Webhook event id is required.');
  $eventType=(string)($payload['event_type']??$payload['type']??'cac.order.status');
  $reference=(string)($payload['reference']??$payload['transaction_reference']??$payload['order_reference']??'');
  return DB::transaction(function() use($provider,$eventId,$eventType,$provided,$payload,$reference) {
   $event=CacWebhookEvent::firstOrCreate(['api_provider_id'=>$provider->id,'event_id'=>$eventId],['event_type'=>$eventType,'signature'=>$provided,'order_reference'=>$reference?:null,'payload'=>$payload,'status'=>'received']);
   if ($event->wasRecentlyCreated===false && $event->status==='processed') return ['duplicate'=>true,'processed'=>true];
   $order=$reference!=='' ? CacOrder::where('reference',$reference)->orWhere('provider_reference',$reference)->lockForUpdate()->first() : null;
   if (!$order) { $event->update(['status'=>'ignored','error_message'=>'CAC order reference not found','processed_at'=>now()]); return ['duplicate'=>false,'processed'=>false]; }
   $status=strtoupper((string)($payload['status']??$payload['transaction_status']??''));
   $map=['SUCCESS'=>'completed','SUCCESSFUL'=>'completed','COMPLETED'=>'completed','ACCEPTED'=>'completed','PENDING'=>'pending_requery','PROCESSING'=>'processing','FAILED'=>'failed','FAILURE'=>'failed','REJECTED'=>'failed'];
   $to=$map[$status]??null;
   if (!$to) { $event->update(['status'=>'ignored','error_message'=>'Unsupported provider status','processed_at'=>now()]); return ['duplicate'=>false,'processed'=>false]; }
   $from=$order->status;
   if ($from!==$to) { $order->update(['status'=>$to,'completed_at'=>$to==='completed'?now():$order->completed_at,'failure_message'=>$to==='failed'?((string)($payload['message']??$payload['error']??'Provider reported failure')):$order->failure_message]); $order->statusHistory()->create(['from_status'=>$from,'to_status'=>$to,'source'=>'webhook','reason'=>'Provider webhook update','metadata'=>['event_id'=>$eventId,'provider_id'=>$provider->id]]); }
   if (!empty($payload['provider_reference']) && !$order->provider_reference) $order->update(['provider_reference'=>(string)$payload['provider_reference'],'api_provider_id'=>$provider->id]);
   $event->update(['status'=>'processed','processed_at'=>now()]);
   return ['duplicate'=>false,'processed'=>true,'status'=>$to];
  });
 }
}