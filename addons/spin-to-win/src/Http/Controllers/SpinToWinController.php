<?php
namespace Semizzy\Addons\SpinToWin\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Semizzy\Addons\SpinToWin\Models\SpinCampaign;
use Semizzy\Addons\SpinToWin\Models\SpinPlay;
use Semizzy\Addons\SpinToWin\Services\SpinToWinService;
use Throwable;

class SpinToWinController extends Controller
{
 public function __construct(private SpinToWinService $service){}
 public function index(Request $r)
 {
  $campaigns=SpinCampaign::query()->where('status','active')->with('prizes')->latest()->get();
  return Inertia::render('SpinToWin',['campaigns'=>$campaigns]);
 }
 public function spin(Request $r,SpinCampaign $campaign)
 {
  try {
   $key=(string)($r->input('operation_key') ?: Str::uuid());
   $play=$this->service->play($r->user()->id,$campaign->id,$key);
   return back()->with('spin_result',['id'=>$play->id,'status'=>$play->status,'prize'=>$play->prize]);
  } catch(Throwable $e) {
   return back()->withErrors(['spin'=>$e->getMessage()]);
  }
 }
}
