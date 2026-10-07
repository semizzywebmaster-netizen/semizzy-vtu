<?php
namespace Semizzy\Addons\Exams\Http\Controllers;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Semizzy\Addons\Exams\Models\ExamProduct;
use Semizzy\Addons\Exams\Models\ExamTransaction;
final class AdminExamResultController extends Controller {
 public function index(){return Inertia::render('Admin/Exams/Results',['products'=>ExamProduct::with('provider')->withCount('transactions')->latest()->get(),'transactions'=>ExamTransaction::with('product')->latest()->paginate(25)]);}
}
