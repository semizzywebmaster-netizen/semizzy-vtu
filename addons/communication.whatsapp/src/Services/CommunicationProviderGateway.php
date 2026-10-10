<?php
namespace Addons\CommunicationWhatsapp\Services;

use App\Models\Communication\DeliveryAttempt;
use App\Models\Communication\Message;
use App\Models\Communication\Provider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CommunicationProviderGateway
{
 public function send(Message $message): DeliveryAttempt
 {
  $providers=Provider::query()->where('channel',$message->channel)->where('enabled',true)->where('paused',false)
   ->where(fn($q)=>$q->whereNull('cooldown_until')->orWhere('cooldown_until','<=',now()))
   ->orderBy('priority')->orderByDesc('weight')->get();
  if($providers->isEmpty()) throw new RuntimeException('No healthy communication provider is available.');

  foreach($providers as $provider){
   $ambiguous=false;
   $attempt=DeliveryAttempt::create(['message_id'=>$message->id,'provider_id'=>$provider->id,'attempt'=>$message->attempts()->count()+1,'status'=>'pending','started_at'=>now()]);
   try{
    $credentials=$provider->credentials ?: [];
    $url=$credentials['url'] ?? $credentials['endpoint'] ?? null;
    if(!$url) throw new RuntimeException('Provider endpoint is not configured.');
    $headers=$credentials['headers'] ?? [];
    $payload=$credentials['payload'] ?? [];
    $payload=array_replace($payload,['to'=>$message->recipient,'message'=>$message->body]);
    $response=Http::withHeaders($headers)->timeout((int)($credentials['timeout'] ?? 30))->send(strtoupper($credentials['method'] ?? 'POST'),$url,['json'=>$payload]);
    if(!$response->successful()){
     if($response->status()===408 || $response->status()>=500){
      $ambiguous=true;
      throw new RuntimeException('Communication provider outcome is unknown; verify delivery status before retrying.');
     }
     throw new RuntimeException('Provider returned HTTP '.$response->status());
    }
    $external=$response->json('id') ?? $response->json('message_id') ?? $response->json('data.id');
    DB::transaction(function()use($attempt,$provider,$message,$external,$response){
      $attempt->update(['status'=>'sent','external_message_id'=>$external,'response'=>substr($response->body(),0,10000),'completed_at'=>now()]);
      $provider->update(['failure_count'=>0,'last_success_at'=>now(),'cooldown_until'=>null]);
      $message->update(['status'=>'sent','external_message_id'=>$external,'sent_at'=>now()]);
    });
    return $attempt->fresh();
   }catch(\Illuminate\Http\Client\ConnectionException $e){
    $attempt->update(['status'=>'unknown','error'=>'Connection failed after request dispatch; delivery outcome is unknown.','completed_at'=>now()]);
    $failures=$provider->failure_count+1;
    $provider->update(['failure_count'=>$failures,'last_failure_at'=>now(),'cooldown_until'=>now()->addMinutes(min(30,max(1,$failures*2)))]);
    $message->update(['status'=>'pending']);
    throw new RuntimeException('Communication provider outcome is unknown; verify delivery status before retrying.',0,$e);
   }catch(\Throwable $e){
    $attempt->update(['status'=>$ambiguous?'unknown':'failed','error'=>substr($e->getMessage(),0,2000),'completed_at'=>now()]);
    $failures=$provider->failure_count+1;
    $provider->update(['failure_count'=>$failures,'last_failure_at'=>now(),'cooldown_until'=>now()->addMinutes(min(30,max(1,$failures*2)))]);
    if($ambiguous){
     $message->update(['status'=>'pending']);
     throw new RuntimeException('Communication provider outcome is unknown; verify delivery status before retrying.',0,$e);
    }
   }
  }
  $message->update(['status'=>'failed','failed_at'=>now()]);
  throw new RuntimeException('All communication providers failed.');
 }
}