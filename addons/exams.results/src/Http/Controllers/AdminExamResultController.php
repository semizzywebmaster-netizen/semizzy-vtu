<?php
namespace Semizzy\Addons\Exams\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\ApiProvider;
use Inertia\Inertia;
use Semizzy\Addons\Exams\Models\ExamProduct;
use Semizzy\Addons\Exams\Models\ExamTransaction;

final class AdminExamResultController extends Controller
{
 public function index(){return Inertia::render('Admin/Exams/Results',['products'=>ExamProduct::with('provider')->withCount('transactions')->latest()->get(),'providers'=>ApiProvider::query()->orderBy('display_name')->get(['id','identifier','display_name']),'transactions'=>ExamTransaction::with('product','user')->latest()->paginate(25)]);}
 public function store(Request $request){
  $data=$request->validate(['key'=>['required','string','max:100'],'exam_body'=>['required','string','max:100'],'name'=>['required','string','max:150'],'currency'=>['required','string','size:3'],'price_minor'=>['required','integer','min:0'],'max_attempts'=>['required','integer','min:1','max:100'],'provider_id'=>['nullable','integer','exists:api_providers,id'],'active'=>['boolean']]);
  $data['key']=strtolower(trim($data['key'])); $data['currency']=strtoupper($data['currency']); $data['active']=(bool)($data['active']??true);
  $product=ExamProduct::create($data);
  return back()->with('success','Exam product created.');
 }
 public function update(Request $request, ExamProduct $product){
  $data=$request->validate(['key'=>['required','string','max:100',Rule::unique('exam_products','key')->ignore($product->id)],'exam_body'=>['required','string','max:100'],'name'=>['required','string','max:150'],'currency'=>['required','string','size:3'],'price_minor'=>['required','integer','min:0'],'max_attempts'=>['required','integer','min:1','max:100'],'provider_id'=>['nullable','integer','exists:api_providers,id'],'active'=>['boolean']]);
  $data['key']=strtolower(trim($data['key'])); $data['currency']=strtoupper($data['currency']); $data['active']=(bool)($data['active']??false); $product->update($data);
  return back()->with('success','Exam product updated.');
 }
 public function toggle(ExamProduct $product){$product->update(['active'=>!$product->active]); return back()->with('success',$product->active?'Exam product enabled.':'Exam product disabled.');}
 public function destroy(ExamProduct $product){
  if($product->transactions()->exists()) return back()->withErrors(['product'=>'Products with transactions cannot be deleted; disable them instead.']);
  $product->delete(); return back()->with('success','Exam product deleted.');
 }
}
