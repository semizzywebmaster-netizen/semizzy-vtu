<?php
namespace Semizzy\Addons\P2p\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Semizzy\Addons\P2p\Models\P2pTransfer;
use Semizzy\Addons\P2p\Services\P2pTransferService;
final class P2pTransferController extends Controller {
 public function index(Request $request){return Inertia::render('P2p/Transfers',['transfers'=>P2pTransfer::where('sender_id',$request->user()->id)->orWhere('recipient_id',$request->user()->id)->latest()->paginate(20)]);}
 public function store(Request $request,P2pTransferService $service){
  $data=$request->validate(['recipient'=>'required|string|max:190','amount_minor'=>'required|integer|min:1','note'=>'nullable|string|max:255','idempotency_key'=>'nullable|string|max:120']);
  $tx=$service->transfer($request->user()->id,$data['recipient'],(string)$data['amount_minor'],$data['note']??null,$data['idempotency_key']??Str::uuid()->toString());
  return response()->json(['reference'=>$tx->reference,'status'=>$tx->status,'amount_minor'=>$tx->amount_minor,'currency'=>$tx->currency],201);
 }
}