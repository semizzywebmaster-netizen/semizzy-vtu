<?php
namespace Semizzy\Addons\Loans\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;
use Semizzy\Addons\Loans\Models\Loan;
use Semizzy\Addons\Loans\Models\LoanProduct;
use Semizzy\Addons\Loans\Services\LoanService;
class LoanController extends Controller {
 public function index(Request $r){return Inertia::render('Loans',['products'=>LoanProduct::where('active',true)->orderBy('name')->get(),'loans'=>Loan::where('user_id',$r->user()->id)->with('product')->latest()->get()]);}
 public function apiIndex(Request $r){return response()->json(['loans'=>Loan::where('user_id',$r->user()->id)->with('product')->latest()->get(),'products'=>LoanProduct::where('active',true)->get()]);}
 public function apply(Request $r,LoanService $s){$d=$r->validate(['product_key'=>['required','string','max:100'],'amount_minor'=>['required','integer','min:1']]);return response()->json(['loan'=>$s->apply($r->user()->id,$d)],201);}
 public function repay(Request $r,string $reference,LoanService $s){$d=$r->validate(['amount_minor'=>['required','integer','min:1']]);$key=trim((string)$r->header('Idempotency-Key'));if($key==='')throw new RuntimeException('Idempotency-Key is required for loan repayments.');$loan=Loan::where('reference',$reference)->where('user_id',$r->user()->id)->firstOrFail();return response()->json(['repayment'=>$s->repay($loan,$r->user()->id,(int)$d['amount_minor'],$key)]);}
}