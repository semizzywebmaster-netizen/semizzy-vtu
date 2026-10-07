<?php
namespace Addons\CommunicationWhatsapp\Http\Controllers;
use App\Models\Communication\Provider;
use Inertia\Inertia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
class CommunicationProviderController
{
 public function page() { return Inertia::render('CommunicationAdmin'); }
 public function index(): JsonResponse {
  return response()->json(['providers'=>Provider::orderBy('channel')->orderBy('priority')->get()->map(fn($p)=>[
   'id'=>$p->id,'channel'=>$p->channel,'name'=>$p->name,'driver'=>$p->driver,'capabilities'=>$p->capabilities,
   'priority'=>$p->priority,'weight'=>$p->weight,'enabled'=>$p->enabled,'paused'=>$p->paused,'failure_count'=>$p->failure_count,
   'cooldown_until'=>$p->cooldown_until,'last_success_at'=>$p->last_success_at,'last_failure_at'=>$p->last_failure_at,
   'credentials_configured'=>!empty($p->credentials),
  ])]);
 }
 public function store(Request $request): JsonResponse {
  $data=$request->validate(['channel'=>'required|in:whatsapp,sms,email,push','name'=>'required|string|max:191','driver'=>'required|string|max:64','credentials'=>'nullable|array','capabilities'=>'nullable|array','priority'=>'nullable|integer|min:1|max:100000','weight'=>'nullable|integer|min:1|max:100000','enabled'=>'nullable|boolean','paused'=>'nullable|boolean']);
  $p=Provider::create($data); return response()->json(['provider'=>$p->makeHidden('credentials')],201);
 }
 public function update(Request $request,Provider $provider): JsonResponse {
  $data=$request->validate(['name'=>'sometimes|string|max:191','driver'=>'sometimes|string|max:64','credentials'=>'sometimes|array','capabilities'=>'sometimes|array','priority'=>'sometimes|integer|min:1|max:100000','weight'=>'sometimes|integer|min:1|max:100000','enabled'=>'sometimes|boolean','paused'=>'sometimes|boolean']);
  $provider->update($data); return response()->json(['provider'=>$provider->fresh()->makeHidden('credentials')]);
 }
 public function destroy(Provider $provider): JsonResponse { $provider->delete(); return response()->json(['deleted'=>true]); }
 public function test(Request $request,Provider $provider): JsonResponse {
  $data=$request->validate(['to'=>'nullable|string|max:191']); $c=$provider->credentials ?: []; $url=$c['url']??$c['endpoint']??null;
  if(!$url)return response()->json(['ok'=>false,'message'=>'Provider endpoint is not configured.'],422);
  try {
   $payload=array_replace($c['payload']??[],array_filter(['to'=>$data['to']??null,'message'=>'SEMIZZY ONE provider test'],fn($v)=>$v!==null));
   $res=Http::withHeaders($c['headers']??[])->timeout((int)($c['timeout']??15))->send(strtoupper($c['method']??'POST'),$url,['json'=>$payload]);
   return response()->json(['ok'=>$res->successful(),'status'=>$res->status(),'response'=>substr($res->body(),0,2000)]);
  } catch (\Throwable $e) { return response()->json(['ok'=>false,'message'=>'Provider connection failed.'],422); }
 }
}