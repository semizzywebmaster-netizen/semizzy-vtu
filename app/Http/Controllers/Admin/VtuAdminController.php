<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceProduct;
use App\Models\VtuTransaction;
use App\Services\Vtu\VtuServiceRegistry;
use App\Services\Vtu\VtuTransactionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
class VtuAdminController extends Controller{
 public function dashboard(){return Inertia::render('Admin/VTU/Dashboard',['metrics'=>['total'=>VtuTransaction::count(),'successful'=>VtuTransaction::where('status','successful')->count(),'pending'=>VtuTransaction::where('status','pending')->count(),'failed'=>VtuTransaction::where('status','failed')->count(),'today'=>VtuTransaction::whereDate('created_at',today())->count()]]);}
 public function services(){return Inertia::render('Admin/VTU/Services',['services'=>Service::whereHas('category',fn($q)=>$q->where('key','vtu-digital-services'))->withCount('products')->orderBy('id')->get()]);}
 public function products(){return Inertia::render('Admin/VTU/Products',['products'=>ServiceProduct::whereHas('service.category',fn($q)=>$q->where('key','vtu-digital-services'))->with('service')->latest()->paginate(50)]);}
 public function transactions(Request $r){$q=VtuTransaction::with(['user','service','product','provider'])->latest();foreach(['status','service_id','api_provider_id','user_id'] as $f)if($r->filled($f))$q->where($f,$r->input($f));return Inertia::render('Admin/VTU/Transactions',['transactions'=>$q->paginate(50)->withQueryString()]);}
 public function requery(VtuTransaction $t,VtuTransactionService $s){$s->requery($t);return back()->with('success','Transaction requery completed.');}
 public function enableService(Service $s){$s->update(['enabled'=>true]);return back()->with('success','Service enabled.');}
 public function disableService(Service $s){$s->update(['enabled'=>false]);return back()->with('success','Service disabled.');}
 public function bootstrap(VtuServiceRegistry $r){$r->bootstrapCatalogue();return back()->with('success','VTU service registry synchronized.');}
}