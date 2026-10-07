<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Semizzy\Addons\Government\Models\GovernmentService;
use Semizzy\Addons\Government\Models\GovernmentApplication;
use Semizzy\Addons\Government\Models\GovernmentDocument;
use Semizzy\Addons\Government\Models\GovernmentCertificate;
class AdminGovernmentServicesController extends Controller {
 public function index(Request $r){
  $q=GovernmentService::query();
  if($r->filled('search')){ $s=$r->string('search')->toString(); $q->where(fn($x)=>$x->where('name','like',"%$s%")->orWhere('service_key','like',"%$s%")->orWhere('agency','like',"%$s%")); }
  if($r->filled('status') && in_array($r->status,['active','inactive'],true)) $q->where('status',$r->status);
  if($r->filled('agency')) $q->where('agency',$r->agency);
  if($r->filled('fulfillment_mode') && in_array($r->fulfillment_mode,['api','manual','api_or_manual'],true)) $q->where('fulfillment_mode',$r->fulfillment_mode);
  $services=$q->latest()->paginate(30)->withQueryString();
  return Inertia::render('Admin/GovernmentServices',[
   'services'=>$services,
   'applications'=>GovernmentApplication::with(['service','documents','certificates'])->latest()->paginate(30),
  ]);
 }
 public function showApplication(GovernmentApplication $application){
  return response()->json(['data'=>$application->load(['service','documents','certificates','user'])]);
 }
 public function storeService(Request $r){$d=$r->validate(['service_key'=>'required|string|max:120|unique:government_services,service_key','name'=>'required|string|max:180','agency'=>'nullable|string|max:180','description'=>'nullable|string','fulfillment_mode'=>'required|in:api,manual,api_or_manual','provider_reference'=>'nullable|string|max:255','price'=>'required|numeric|min:0','currency'=>'required|string|size:3','status'=>'required|in:active,inactive','requirements'=>'nullable|array','metadata'=>'nullable|array']);$d['currency']=strtoupper($d['currency']);return response()->json(['data'=>GovernmentService::create($d)],201);}
 public function updateService(Request $r,GovernmentService $service){$d=$r->validate(['name'=>'sometimes|required|string|max:180','agency'=>'nullable|string|max:180','description'=>'nullable|string','fulfillment_mode'=>'sometimes|required|in:api,manual,api_or_manual','provider_reference'=>'nullable|string|max:255','price'=>'sometimes|required|numeric|min:0','currency'=>'sometimes|required|string|size:3','status'=>'sometimes|required|in:active,inactive','requirements'=>'nullable|array','metadata'=>'nullable|array']);if(isset($d['currency']))$d['currency']=strtoupper($d['currency']);$service->fill($d)->save();return response()->json(['data'=>$service->fresh()]);}
  public function addRequirement(Request $r, GovernmentService $service){
   $d=$r->validate(['key'=>'required|string|max:100','label'=>'required|string|max:180','type'=>'required|in:text,textarea,email,number,date,select,checkbox,file,json','required'=>'sometimes|boolean','options'=>'nullable|array','accept'=>'nullable|string|max:255']);
   $requirements=$service->requirements??[];
   if(collect($requirements)->contains(fn($x)=>($x['key']??null)===$d['key'])) return response()->json(['message'=>'Requirement key already exists.'],422);
   $d['required']=(bool)($d['required']??false); $d['options']=$d['options']??[]; $requirements[]=$d;
   $service->requirements=$requirements; $service->save();
   return response()->json(['data'=>$service->fresh()]);
  }
  public function updateRequirement(Request $r, GovernmentService $service, string $key){
   $d=$r->validate(['label'=>'sometimes|required|string|max:180','type'=>'sometimes|required|in:text,textarea,email,number,date,select,checkbox,file,json','required'=>'sometimes|boolean','options'=>'nullable|array','accept'=>'nullable|string|max:255']);
   $requirements=$service->requirements??[]; $found=false;
   foreach($requirements as &$item){ if(($item['key']??null)===$key){$item=array_merge($item,$d);$found=true;break;} }
   if(!$found) return response()->json(['message'=>'Requirement not found.'],404);
   $service->requirements=$requirements; $service->save();
   return response()->json(['data'=>$service->fresh()]);
  }
  public function deleteRequirement(GovernmentService $service, string $key){
   $requirements=array_values(array_filter($service->requirements??[],fn($x)=>($x['key']??null)!==$key));
   $service->requirements=$requirements; $service->save();
   return response()->json(['data'=>$service->fresh()]);
  }
 public function updateApplicationStatus(Request $r,GovernmentApplication $application){$d=$r->validate(['status'=>'required|in:draft,pending_payment,paid,processing,awaiting_documents,submitted,completed,failed,cancelled']);$application->status=$d['status'];if($d['status']==='submitted'&&!$application->submitted_at)$application->submitted_at=now();if($d['status']==='completed'&&!$application->completed_at)$application->completed_at=now();$application->save();return response()->json(['data'=>$application->fresh()]);}
 public function reviewDocument(Request $r,GovernmentDocument $document){$d=$r->validate(['status'=>'required|in:pending,approved,rejected','review_note'=>'nullable|string|max:2000']);$document->status=$d['status'];$document->review_note=$d['review_note']??null;$document->reviewed_by=$r->user()->id;$document->reviewed_at=now();$document->save();return response()->json(['data'=>$document->fresh()]);}
 public function storeCertificate(Request $r,GovernmentApplication $application){
  $d=$r->validate(['certificate_type'=>'required|string|max:120','certificate_number'=>'nullable|string|max:180','certificate'=>'required|file|max:15360|mimes:pdf,jpg,jpeg,png']);
  $file=$d['certificate']; $path=$file->store('government-certificates/'.$application->id,'local');
  $certificate=GovernmentCertificate::create(['application_id'=>$application->id,'certificate_type'=>$d['certificate_type'],'certificate_number'=>$d['certificate_number']??null,'disk'=>'local','path'=>$path,'issued_at'=>now(),'status'=>'active']);
  if($application->status!=='completed'){$application->update(['status'=>'completed','completed_at'=>now()]);}
  return response()->json(['data'=>$certificate],201);
 }
 public function updateCertificate(Request $r,GovernmentCertificate $certificate){
  $d=$r->validate(['status'=>'required|in:active,revoked,expired','certificate_number'=>'nullable|string|max:180']);
  $certificate->fill($d)->save(); return response()->json(['data'=>$certificate->fresh()]);
 }
}