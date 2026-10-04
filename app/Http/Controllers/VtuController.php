<?php
namespace App\Http\Controllers;
use App\Models\ServiceProduct;
use App\Models\VtuTransaction;
use App\Models\ApiProvider;
use App\Services\Vtu\VtuBulkService;
use App\Services\Vtu\VtuPayloadValidator;
use App\Services\Vtu\VtuServiceRegistry;
use App\Services\Vtu\VtuTransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
class VtuController extends Controller{
 public function index(VtuServiceRegistry $r){return Inertia::render('VTU/Services',['services'=>$r->services()->map(fn($s)=>['id'=>$s->id,'key'=>$s->key,'name'=>$s->name,'description'=>$s->description,'metadata'=>$s->metadata,'products'=>$s->products->map(fn($p)=>['id'=>$p->id,'key'=>$p->key,'name'=>$p->name,'metadata'=>$p->metadata])])]);}
 public function apiServices(VtuServiceRegistry $r){return response()->json(['data'=>$r->services()->map(fn($s)=>['id'=>$s->id,'key'=>$s->key,'name'=>$s->name,'description'=>$s->description,'metadata'=>$s->metadata,'products'=>$s->products->map(fn($p)=>['id'=>$p->id,'key'=>$p->key,'name'=>$p->name,'metadata'=>$p->metadata])])]);}
 public function quote(Request $r,VtuTransactionService $s){$d=$r->validate(['product_id'=>['required','integer','exists:service_products,id'],'tier'=>['nullable','string','max:30']]);return response()->json(['data'=>$s->quote(ServiceProduct::findOrFail($d['product_id']),$d['tier']??'USER')]);}
 public function store(Request $r,VtuTransactionService $s,VtuPayloadValidator $v){$d=$r->validate(['product_id'=>['required','integer','exists:service_products,id'],'idempotency_key'=>['nullable','string','max:120'],'payload'=>['required','array']]);$p=ServiceProduct::with('service')->findOrFail($d['product_id']);abort_unless($p->enabled&&$p->service->enabled,422,'Service is unavailable.');$v->validate($p->service,$d['payload']);$tx=$s->process($s->create($r->user()->id,$p,$d['payload'],$r->user()->role,$d['idempotency_key']??null));return response()->json(['data'=>$this->present($tx)],$tx->status==='failed'?422:201);}
 public function bulk(Request $r,VtuBulkService $b){$d=$r->validate(['items'=>['required','array','min:1','max:500'],'items.*.product_id'=>['required','integer','exists:service_products,id'],'items.*.payload'=>['required','array'],'items.*.idempotency_key'=>['nullable','string','max:120'],'idempotency_key'=>['nullable','string','max:160']]);return response()->json(['data'=>$b->execute($r->user()->id,$d['items'],$r->user()->role,$d['idempotency_key']??null)],201);}
 public function history(Request $r){return response()->json(['data'=>VtuTransaction::with('service','product')->where('user_id',$r->user()->id)->latest()->paginate(25)]);}
 public function show(Request $r,VtuTransaction $t){abort_unless($t->user_id===$r->user()->id,404);return response()->json(['data'=>$this->present($t->load('service','product'))]);}
 public function webhook(Request $r,ApiProvider $provider,VtuTransactionService $service){$secret=(string)($provider->credentials['webhook_secret']??'');abort_unless($secret!=='',401);$signature=(string)$r->header('X-Webhook-Signature','');$raw=$r->getContent();$expected=hash_hmac('sha256',$raw,$secret);if(str_starts_with($signature,'sha256='))$signature=substr($signature,7);abort_unless($signature!==''&&hash_equals($expected,$signature),401);$body=$r->all();$reference=$body['provider_reference']??$body['reference']??$body['data']['reference']??null;if(!$reference)return response()->json(['status'=>'ignored']);$tx=VtuTransaction::query()->where('api_provider_id',$provider->id)->where('provider_reference',(string)$reference)->first();if($tx)$service->requery($tx);return response()->json(['status'=>'accepted']);}
 public function requery(Request $r,VtuTransaction $t,VtuTransactionService $s){abort_unless($t->user_id===$r->user()->id,404);return response()->json(['data'=>$this->present($s->requery($t))]);}
 private function present(VtuTransaction $t):array{return ['id'=>$t->id,'reference'=>$t->reference,'service'=>$t->service?->name,'product'=>$t->product?->name,'status'=>$t->status,'amount_minor'=>$t->amount_minor,'fee_minor'=>$t->fee_minor,'total_minor'=>$t->total_minor,'currency'=>$t->currency,'recipient'=>$t->recipient,'provider_reference'=>$t->provider_reference,'failure_message'=>$t->failure_message,'created_at'=>$t->created_at?->toIso8601String(),'completed_at'=>$t->completed_at?->toIso8601String()];}
}