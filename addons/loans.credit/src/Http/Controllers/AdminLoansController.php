<?php
namespace Semizzy\Addons\Loans\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Semizzy\Addons\Loans\Models\Loan;
use Semizzy\Addons\Loans\Models\LoanProduct;
use Semizzy\Addons\Loans\Services\LoanService;
class AdminLoansController extends Controller {
 public function index(){return \Inertia\Inertia::render('Admin/Loans',['products'=>LoanProduct::orderBy('name')->get(),'loans'=>Loan::with(['product','user'])->latest()->paginate(25)]);}
 public function approve(string $reference,LoanService $s){$loan=Loan::where('reference',$reference)->firstOrFail();return response()->json(['loan'=>$s->approve($loan)]);}
 public function reject(Request $r,string $reference,LoanService $s){$d=$r->validate(['reason'=>['required','string','max:1000']]);$loan=Loan::where('reference',$reference)->firstOrFail();return response()->json(['loan'=>$s->reject($loan,$d['reason'])]);}
 public function disburse(string $reference,LoanService $s){$loan=Loan::where('reference',$reference)->firstOrFail();return response()->json(['loan'=>$s->disburse($loan)]);}
}