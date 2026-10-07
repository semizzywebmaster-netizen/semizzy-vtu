<?php
namespace Semizzy\Addons\Exams\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Semizzy\Addons\Exams\Models\ExamProduct;
use Semizzy\Addons\Exams\Services\ExamResultService;
final class ExamResultController extends Controller {
 public function index(){return Inertia::render('Exams/Results',['products'=>ExamProduct::where('active',true)->latest()->get(['id','key','exam_body','name','currency','price_minor','max_attempts'])]);}
 public function check(Request $request, ExamResultService $service){
  $data=$request->validate(['product_id'=>'required|integer|exists:exam_products,id','candidate_identifier'=>'required|string|max:120','candidate_name'=>'nullable|string|max:255','idempotency_key'=>'required|string|max:120']);
  $tx=$service->check($request->user()->id,(int)$data['product_id'],$data['candidate_identifier'],$data['candidate_name']??null,$data['idempotency_key']);
  return response()->json(['reference'=>$tx->reference,'status'=>$tx->status,'provider_reference'=>$tx->provider_reference,'result'=>$tx->result_payload,'message'=>$tx->error],$tx->status==='failed'?422:202);
 }
}