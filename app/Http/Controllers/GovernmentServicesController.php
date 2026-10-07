<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Semizzy\Addons\Government\Models\GovernmentService;
use Semizzy\Addons\Government\Models\GovernmentApplication;
use Semizzy\Addons\Government\Services\GovernmentServicesService;
class GovernmentServicesController extends Controller {
 public function index(){return Inertia::render('GovernmentServices',['services'=>GovernmentService::where('status','active')->latest()->get()]);}
 public function apply(Request $r,GovernmentService $service,GovernmentServicesService $engine){
  $data=$r->validate(['data'=>'nullable|array']);
  $application=$engine->createApplication($r->user()->id,$service,$data['data']??[]);
  return response()->json(['data'=>$application],201);
 }
 public function uploadDocument(Request $r,GovernmentApplication $application,GovernmentServicesService $engine){
  abort_unless($application->user_id===$r->user()->id,404);
  $data=$r->validate([
   'document_type'=>'required|string|max:100',
   'document'=>'required|file|max:10240|mimes:pdf,jpg,jpeg,png',
  ]);
  $file=$data['document'];
  $path=$file->store('government-applications/'.$application->id,'local');
  $doc=$engine->addDocument($application,[
   'document_type'=>$data['document_type'],'disk'=>'local','path'=>$path,
   'original_name'=>$file->getClientOriginalName(),'mime_type'=>$file->getMimeType(),
   'size_bytes'=>$file->getSize(),
  ]);
  return response()->json(['data'=>$doc],201);
 }
 public function submit(Request $r,GovernmentApplication $application,GovernmentServicesService $engine){
  abort_unless($application->user_id===$r->user()->id,404);
  $service=$application->service;
  $requireReview=(bool)($service->metadata['require_document_review'] ?? true);
  $application=$engine->submitApplication($application,$requireReview);
  return response()->json(['data'=>$application]);
 }
 public function show(Request $r,GovernmentApplication $application){
  abort_unless($application->user_id===$r->user()->id,404);
  return response()->json(['data'=>$application->load(['service','documents','certificates'])]);
 }
}