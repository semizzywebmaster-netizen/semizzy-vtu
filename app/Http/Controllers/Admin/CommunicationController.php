<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\CommunicationCampaign;
use App\Services\Communication\CommunicationCenterService;
use Illuminate\Http\Request;
use Inertia\Inertia;
class CommunicationController extends Controller {
 public function index(){return Inertia::render('Admin/CommunicationCenter',['campaigns'=>CommunicationCampaign::latest()->paginate(20)]);}
 public function store(Request $request,CommunicationCenterService $service){
  abort_unless($request->user()->hasPermission('communications.manage'),403);
  $data=$request->validate(['type'=>'required|in:announcement,direct_message,marketing,service_notification,security_alert','title'=>'required|string|max:180','message'=>'required|string|max:10000','url'=>'nullable|url|max:500','targets'=>'required|array','channels'=>'required|array','channels.*'=>'in:web_push,whatsapp,sms,email']);
  $campaign=CommunicationCampaign::create([...$data,'created_by'=>$request->user()->id]);
  if($request->boolean('send_now')) $service->send($campaign);
  return back()->with('success',$request->boolean('send_now')?'Communication sent.':'Campaign saved.');
 }
 public function send(CommunicationCampaign $campaign,CommunicationCenterService $service){abort_unless(auth()->user()->hasPermission('communications.manage'),403);$service->send($campaign);return back()->with('success','Communication sent.');}
}