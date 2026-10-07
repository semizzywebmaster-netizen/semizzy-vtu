<?php
namespace Semizzy\Addons\GiftCards\Http\Controllers\Admin;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Semizzy\Addons\GiftCards\Services\GiftCardWalletService;
use Semizzy\Addons\GiftCards\Models\{GiftCardOrder,GiftCardProduct,GiftCardRefund};

class GiftCardsController extends Controller
{
 public function __construct(private GiftCardWalletService $wallet){}
 public function index(){return Inertia::render('Admin/GiftCards',['products'=>GiftCardProduct::latest()->paginate(30),'orders'=>GiftCardOrder::latest()->paginate(30),'refunds'=>GiftCardRefund::latest()->paginate(30)]);}
 public function storeProduct(Request $request){$data=$request->validate(['name'=>'required|string|max:150','brand'=>'required|string|max:100','code'=>'required|string|max:100','country_code'=>'nullable|string|max:8','currency'=>'required|string|max:8','denomination_type'=>'required|in:fixed,variable','denominations'=>'nullable|array','min_amount'=>'nullable|numeric|min:0','max_amount'=>'nullable|numeric|min:0','provider_price'=>'required|numeric|min:0','sale_price'=>'required|numeric|min:0','fulfillment_mode'=>'required|in:provider,inventory','metadata'=>'nullable|array']); GiftCardProduct::create($data+['enabled'=>true]); return back()->with('success','Gift-card product created.');}
 public function toggleProduct(GiftCardProduct $product){$product->update(['enabled'=>!$product->enabled]);return back()->with('success','Gift-card product status updated.');}
 public function requery(GiftCardOrder $order){return back()->with('success','Gift-card requery queued.');}
 public function requestRefund(Request $request,GiftCardOrder $order){$data=$request->validate(['note'=>'nullable|string|max:1000']);if(!in_array($order->status,['fulfilled','failed'],true))throw new RuntimeException('Only completed or failed orders can be refunded.');GiftCardRefund::firstOrCreate(['gift_card_order_id'=>$order->id],['user_id'=>$order->user_id,'amount'=>$order->total,'note'=>$data['note']??null]);return back()->with('success','Refund request recorded.');}
 public function approveRefund(Request $request,GiftCardRefund $refund){DB::transaction(function()use($request,$refund){$refund=GiftCardRefund::whereKey($refund->id)->lockForUpdate()->firstOrFail();if($refund->status==='approved')return;if($refund->status!=='pending')throw new RuntimeException('Refund is not pending.');$order=GiftCardOrder::whereKey($refund->gift_card_order_id)->lockForUpdate()->firstOrFail();$this->wallet->refund($order);$refund->update(['status'=>'approved','approved_by'=>$request->user()->id,'note'=>$request->input('note',$refund->note)]);$order->update(['status'=>'refunded']);});return back()->with('success','Refund approved and wallet credited.');}
}