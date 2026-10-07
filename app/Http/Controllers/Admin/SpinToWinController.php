<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\Security\SecurityEventLogger;
use Semizzy\Addons\SpinToWin\Models\SpinCampaign;
use Semizzy\Addons\SpinToWin\Models\SpinPrize;
use Semizzy\Addons\SpinToWin\Models\SpinPlay;
class SpinToWinController extends Controller {
 public function __construct(private readonly SecurityEventLogger $audit) {}
 public function index(){return Inertia::render('Admin/SpinToWin',['campaigns'=>SpinCampaign::query()->with('prizes')->latest()->get()]);}
 public function storeCampaign(Request $r){$d=$r->validate(['name'=>'required|string|max:160','status'=>'required|in:draft,active,inactive','starts_at'=>'nullable|date','ends_at'=>'nullable|date|after_or_equal:starts_at','daily_play_limit'=>'required|integer|min:0','total_play_limit'=>'nullable|integer|min:1','eligibility'=>'nullable|array','tier_restrictions'=>'nullable|array']);if(($d['status']??'draft')==='active'&&empty($d['daily_play_limit']))throw new \InvalidArgumentException('Daily limit must be greater than zero for an active campaign.');SpinCampaign::create($d);$this->audit->record('spin.admin.campaign_created','info',['campaign'=>$d['name']]);return back()->with('success','Spin campaign created.');}
 public function updateCampaign(Request $r,SpinCampaign $campaign){$d=$r->validate(['name'=>'sometimes|string|max:160','status'=>'sometimes|in:draft,active,inactive','starts_at'=>'nullable|date','ends_at'=>'nullable|date|after_or_equal:starts_at','daily_play_limit'=>'sometimes|integer|min:0','total_play_limit'=>'nullable|integer|min:1','eligibility'=>'nullable|array','tier_restrictions'=>'nullable|array']);$campaign->update($d);$this->audit->record('spin.admin.campaign_updated','info',['campaign_id'=>$campaign->id]);return back()->with('success','Spin campaign updated.');}
 public function storePrize(Request $r,SpinCampaign $campaign){$d=$r->validate(['name'=>'required|string|max:160','prize_type'=>'required|in:wallet,coupon,manual,no_win','amount'=>'nullable|numeric|min:0','currency'=>'nullable|string|size:3','weight'=>'required|numeric|min:0','max_wins'=>'nullable|integer|min:1','coupon_code'=>'nullable|string|max:100','eligibility'=>'nullable|array','active'=>'boolean']);$d['currency']=strtoupper($d['currency']??'NGN');if($d['prize_type']==='wallet'&&(float)$d['amount']<=0)throw new \InvalidArgumentException('Wallet prizes must have a positive amount.');SpinPrize::create($d+['campaign_id'=>$campaign->id]);$this->audit->record('spin.admin.prize_created','info',['campaign_id'=>$campaign->id,'prize_type'=>$d['prize_type']]);return back()->with('success','Prize added.');}
 public function updatePrize(Request $r,SpinPrize $prize){$d=$r->validate(['name'=>'sometimes|string|max:160','prize_type'=>'sometimes|in:wallet,coupon,manual,no_win','amount'=>'nullable|numeric|min:0','weight'=>'sometimes|numeric|min:0','max_wins'=>'nullable|integer|min:1','active'=>'boolean','coupon_code'=>'nullable|string|max:100','eligibility'=>'nullable|array']);$prize->update($d);$this->audit->record('spin.admin.prize_updated','info',['prize_id'=>$prize->id]);return back()->with('success','Prize updated.');}
 public function plays(){return Inertia::render('Admin/SpinToWinPlays',['plays'=>SpinPlay::query()->with('prize')->latest('id')->paginate(50)]);}
}