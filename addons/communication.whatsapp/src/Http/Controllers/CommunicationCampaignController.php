<?php
namespace Addons\CommunicationWhatsapp\Http\Controllers;

use Addons\CommunicationWhatsapp\Services\CommunicationCampaignService;
use Addons\CommunicationWhatsapp\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CommunicationCampaignController
{
 public function index(Request $request): JsonResponse
 {
  $q=Campaign::with('template')->withCount(['messages'])->latest();
  if($request->filled('status')) $q->where('status',$request->string('status'));
  if($request->filled('channel')) $q->where('channel',$request->string('channel'));
  return response()->json($q->paginate(min(max((int)$request->input('per_page',25),1),100)));
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
  $limit=min(max((int)$request->input('limit',500),1),500);
  return response()->json(['campaign'=>$campaign->fresh(),'result'=>$service->process($campaign,$limit)]);
 }
 public function pause(Campaign $campaign): JsonResponse
 {
  if(in_array($campaign->status,['completed','failed','cancelled'],true)) return response()->json(['message'=>'Campaign cannot be paused in its current state.'],422);
  $campaign->update(['status'=>'paused']); return response()->json(['campaign'=>$campaign->fresh()]);
 }
 public function resume(Campaign $campaign): JsonResponse
 {
  if($campaign->status!=='paused') return response()->json(['message'=>'Only paused campaigns can be resumed.'],422);
  $campaign->update(['status'=>'scheduled','scheduled_at'=>now(),'completed_at'=>null]); return response()->json(['campaign'=>$campaign->fresh()]);
 }
 public function cancel(Campaign $campaign): JsonResponse
 {
  if(in_array($campaign->status,['completed','cancelled'],true)) return response()->json(['message'=>'Campaign cannot be cancelled in its current state.'],422);
  $campaign->update(['status'=>'cancelled','completed_at'=>now()]); return response()->json(['campaign'=>$campaign->fresh()]);
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
