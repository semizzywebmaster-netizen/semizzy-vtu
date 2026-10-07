<?php
namespace Addons\CommunicationWhatsapp\Http\Controllers;

use Addons\CommunicationWhatsapp\Services\CommunicationCampaignService;
use App\Models\Communication\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CommunicationCampaignController
{
 public function index(Request $request): JsonResponse
 {
  $q=Campaign::with('template')->withCount(['messages'])->latest();\n  if($request->filled('status')) $q->where('status',$request->string('status'));\n  if($request->filled('channel')) $q->where('channel',$request->string('channel'));\n  return response()->json($q->paginate(min(max((int)$request->input('per_page',25),1),100)));
 }
 public function store(Request $request,CommunicationCampaignService $service): JsonResponse
 {
  $data=$request->validate([
   'name'=>'required|string|max:191','channel'=>'required|in:whatsapp,sms,email,push',
   'template_id'=>'nullable|exists:communication_templates,id','content'=>'nullable|string',
   'audience'=>'required|array','audience.user_ids'=>'nullable|array','audience.tier'=>'nullable|array',
   'audience.role'=>'nullable|array','audience.account_type'=>'nullable|array','audience.country'=>'nullable|array',
   'audience.state'=>'nullable|array','audience.segment'=>'nullable|array','scheduled_at'=>'nullable|date'
  ]);
  if(empty($data['template_id']) && blank($data['content'])) return response()->json(['message'=>'Template or content is required.'],422);
  return response()->json(['campaign'=>$service->create($data,$request->user())],201);
 }
 public function show(Campaign $campaign): JsonResponse
 {
  return response()->json($campaign->load('template')->loadCount('messages'));
 }
 public function run(Request $request,Campaign $campaign,CommunicationCampaignService $service): JsonResponse
 {
  return response()->json(['campaign'=>$campaign->fresh(),'result'=>$service->process($campaign,(int)$request->input('limit',500))]);
 }
 public function runDue(CommunicationCampaignService $service): JsonResponse
 {
  $result=['campaigns'=>0,'processed'=>0,'sent'=>0,'failed'=>0,'skipped'=>0];
  Campaign::query()->whereIn('status',['draft','scheduled'])->where(fn($q)=>$q->whereNull('scheduled_at')->orWhere('scheduled_at','<=',now()))->orderBy('id')->limit(50)->get()->each(function($c)use($service,&$result){
   $r=$service->process($c,500); $result['campaigns']++; foreach(['processed','sent','failed','skipped'] as $k)$result[$k]+=$r[$k];
  });
  return response()->json($result);
 }
}
