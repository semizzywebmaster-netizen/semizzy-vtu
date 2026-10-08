<?php
namespace Addons\VirtualCards\Http\Controllers;
use Addons\VirtualCards\Models\VirtualCard;
use Addons\VirtualCards\Services\VirtualCardService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
class VirtualCardController extends Controller {
 public function __construct(private VirtualCardService $service){}
 private function own(Request $r,VirtualCard $card): void { abort_unless((int)$card->user_id===(int)$r->user()->id,404); }
 public function index(Request $r){return inertia('VirtualCards',['cards'=>VirtualCard::where('user_id',$r->user()->id)->with('transactions')->latest()->get()->map(fn($c)=>$c->makeHidden(['encrypted_reference']))]);}
 public function request(Request $r){$data=$r->validate(['currency'=>'nullable|string|size:3']);$this->service->request($r->user()->id,$data['currency']??'NGN');return back()->with('success','Virtual card request created and is awaiting provider issuance.');}
 public function limit(Request $r,VirtualCard $card){$this->own($r,$card);$data=$r->validate(['limit_minor'=>'nullable|integer|min:0']);$this->service->setSpendingLimit($card,$data['limit_minor']??null);return back()->with('success','Card spending limit updated.');}
 public function freeze(Request $r,VirtualCard $card){$this->own($r,$card);$this->service->setStatus($card,'frozen');return back()->with('success','Virtual card frozen.');}
 public function unfreeze(Request $r,VirtualCard $card){$this->own($r,$card);$this->service->setStatus($card,'active');return back()->with('success','Virtual card unfrozen.');}
}