<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Semizzy\Addons\SpinToWin\Models\SpinCampaign;
use Semizzy\Addons\SpinToWin\Services\SpinToWinService;
class SpinToWinController extends Controller {
 public function index(){return Inertia::render('SpinToWin/Index',['campaigns'=>SpinCampaign::query()->with('prizes')->where('status','active')->orderBy('id')->get()->map(fn($c)=>['id'=>$c->id,'name'=>$c->name,'daily_play_limit'=>$c->daily_play_limit,'prizes'=>$c->prizes->map(fn($p)=>['id'=>$p->id,'name'=>$p->name,'prize_type'=>$p->prize_type,'amount'=>$p->amount,'currency'=>$p->currency,'weight'=>(float)$p->weight])])]);}
 public function spin(Request $request,SpinCampaign $campaign,SpinToWinService $service){$data=$request->validate(['operation_key'=>'required|string|max:190']);try{$play=$service->play((int)$request->user()->id,$campaign->id,$data['operation_key']);if($play->status==='won'&&$play->prize?->prize_type==='wallet')$service->issueMonetaryReward($play,(int)$request->user()->id);return back()->with('success',$play->prize?->name??'No win this time.');}catch(\Throwable $e){return back()->with('error',$e->getMessage());}}
}