<?php
namespace Semizzy\Addons\Escrow\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use RuntimeException;
use Semizzy\Addons\Escrow\Models\EscrowTransaction;
use Semizzy\Addons\Escrow\Services\EscrowService;
final class EscrowController extends Controller {
 private function minor(string $v):string{if(!preg_match('/^\d+(?:\.\d{1,2})?$/',trim($v)))throw new RuntimeException('Amount must be a valid NGN amount.');[$w,$f]=array_pad(explode('.',trim($v),2),2,'');$f=str_pad($f,2,'0');return ltrim($w.$f,'0')?:'0';}
 private function fail(RuntimeException $e):JsonResponse{return response()->json(['success'=>false,'message'=>$e->getMessage()],422);}
 public function index(Request $r){return Inertia::render('Escrow/Index',['escrows'=>EscrowTransaction::where('buyer_id',$r->user()->id)->orWhere('seller_id',$r->user()->id)->latest()->paginate(20)]);}
 public function store(Request $r,EscrowService $s){try{$d=$r->validate(['seller'=>'required|string|max:190','amount'=>'required|string|max:30','title'=>'required|string|max:190','description'=>'nullable|string|max:5000','idempotency_key'=>'nullable|string|max:120']);$s->create($r->user()->id,$d['seller'],$this->minor($d['amount']),$d['title'],$d['description']??null,$d['idempotency_key']??Str::uuid()->toString());return redirect()->route('escrow.index')->with('success','Escrow funded successfully.');}catch(RuntimeException $e){return $this->fail($e);}}
 public function release(Request $r,int $escrow,EscrowService $s):JsonResponse{try{$x=$s->release($r->user()->id,$escrow);return response()->json(['success'=>true,'reference'=>$x->reference,'status'=>$x->status]);}catch(RuntimeException $e){return $this->fail($e);}}
 public function cancel(Request $r,int $escrow,EscrowService $s):JsonResponse{try{$x=$s->cancel($r->user()->id,$escrow);return response()->json(['success'=>true,'reference'=>$x->reference,'status'=>$x->status]);}catch(RuntimeException $e){return $this->fail($e);}}
 public function dispute(Request $r,int $escrow,EscrowService $s):JsonResponse{try{$d=$r->validate(['reason'=>'required|string|max:190','details'=>'nullable|string|max:5000']);$x=$s->dispute($r->user()->id,$escrow,$d['reason'],$d['details']??null);return response()->json(['success'=>true,'dispute_id'=>$x->id,'status'=>'disputed'],201);}catch(RuntimeException $e){return $this->fail($e);}}
 public function admin(Request $r){return Inertia::render('Admin/Escrow/Index',['escrows'=>EscrowTransaction::with(['buyer:id,name,email','seller:id,name,email'])->latest()->paginate(30)]);}
 public function expire(int $escrow, EscrowService $s): JsonResponse { try { $x=$s->expire($escrow); return response()->json(['success'=>true,'reference'=>$x->reference,'status'=>$x->status]); } catch(RuntimeException $e) { return $this->fail($e); }}
 public function resolve(Request $r,int $escrow,EscrowService $s):JsonResponse{try{$d=$r->validate(['decision'=>'required|in:release,refund','note'=>'nullable|string|max:5000']);$x=$s->resolve($r->user()->id,$escrow,$d['decision'],$d['note']??null);return response()->json(['success'=>true,'reference'=>$x->reference,'status'=>$x->status]);}catch(RuntimeException $e){return $this->fail($e);}}
}