<?php
namespace Semizzy\Addons\Exams\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Semizzy\Addons\Exams\Models\ExamProduct;
final class ExamResultController extends Controller {
 public function index(){return Inertia::render('Exams/Results',['products'=>ExamProduct::where('active',true)->latest()->get(['id','key','exam_body','name','currency','price_minor','max_attempts'])]);}
 public function check(Request $request){
  $data=$request->validate(['product_id'=>'required|integer|exists:exam_products,id','candidate_identifier'=>'required|string|max:120','candidate_name'=>'nullable|string|max:255','idempotency_key'=>'required|string|max:120']);
  return response()->json(['message'=>'Exam result check request accepted for processing.','idempotency_key'=>$data['idempotency_key']],202);
 }
}
