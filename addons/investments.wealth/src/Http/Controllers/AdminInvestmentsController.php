<?php
namespace Semizzy\Addons\Investments\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Semizzy\Addons\Investments\Models\InvestmentAccount;
use Semizzy\Addons\Investments\Models\InvestmentProduct;
class AdminInvestmentsController extends Controller { public function index(){return inertia('Admin/Investments',['products'=>InvestmentProduct::latest()->get(),'investments'=>InvestmentAccount::with('product')->latest()->paginate(25)]);} public function toggle(Request $r,InvestmentProduct $product){$product->active=!$product->active;$product->saveOrFail();return back();} }