<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
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
 public function show(Request $r,GovernmentApplication $application){
  abort_unless($application->user_id===$r->user()->id,404);
  return response()->json(['data'=>$application->load(['service','documents','certificates'])]);
 }
}