<?php
namespace Semizzy\Addons\TravelTickets\Services;
use App\Models\ApiProvider;use App\Models\ProviderEndpoint;use Illuminate\Support\Facades\Http;
class TravelProviderGateway{
 public function candidates(string $capability):array{return ApiProvider::eligibleForNewTransactions()->whereJsonContains('capabilities',$capability)->orderBy('priority')->get()->all();}
 public function request(string $capability,string $operation,array $payload):array{
  foreach($this->candidates($capability) as $provider){
   $endpoint=$this->endpoint($provider->id,$operation);if(!$endpoint)continue;
   $url=$endpoint->full_url ?: rtrim((string)$provider->base_url,'/').'/'.ltrim((string)$endpoint->path,'/');
   $request=Http::timeout(max(1,(int)($provider->timeout_seconds?:30)))->acceptJson();
   foreach(($endpoint->headers?:[]) as $k=>$v)$request=$request->withHeaders([$k=>$v]);
   $request=$this->authenticate($request,$provider,$endpoint);
   try{$response=strtoupper($endpoint->method)==='GET'?$request->get($url,$payload):$request->send(strtoupper($endpoint->method?:'POST'),$url,['json'=>$payload]);if($response->successful())return ['provider'=>$provider,'endpoint'=>$endpoint,'response'=>$response->json()];}
   catch(\Throwable $e){continue;}
  }
  throw new \RuntimeException('No eligible travel provider completed the requested operation.');
 }
 private function endpoint(int $providerId,string $operation):?ProviderEndpoint{return ProviderEndpoint::where('api_provider_id',$providerId)->where('operation',$operation)->where('enabled',true)->first();}
 private function authenticate($request,ApiProvider $provider,ProviderEndpoint $endpoint){
  $credentials=$provider->credentials?:[];$mode=$endpoint->auth_mode?:$provider->auth_type;
  if(in_array($mode,['bearer','token','api_key'],true)&&!empty($credentials['api_key']))return $request->withToken($credentials['api_key']);
  if($mode==='basic'&&!empty($credentials['username']))return $request->withBasicAuth($credentials['username'],$credentials['password']??'');
  return $request;
 }
}