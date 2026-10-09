<?php
namespace Semizzy\Addons\Education\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
final class EducationReferenceCatalogueController extends Controller {
 public function index(Request $request) {
  $q=DB::table('education_reference_catalogue');
  if($request->filled('kind')) $q->where('kind',$request->query('kind'));
  if($request->filled('category')) $q->where('category',$request->query('category'));
  if($request->filled('q')) $q->where('name','like','%'.$request->query('q').'%');
  return Inertia::render('Admin/Education/ReferenceCatalogue',[
   'entries'=>$q->orderBy('kind')->orderBy('name')->paginate(40)->withQueryString(),
   'filters'=>$request->only(['kind','category','q']),
   'flash'=>['success'=>session('success'),'error'=>session('error')],
   'validationErrors'=>$request->session()->get('errors') ? $request->session()->get('errors')->getBag('default')->all() : [],
   'categories'=>DB::table('education_reference_categories')->orderBy('sort_order')->orderBy('name')->get(),
   'categoryOptions'=>DB::table('education_reference_catalogue')->where('kind','school')->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
  ]);
 }
 public function store(Request $request) {
  $data=$request->validate([
   'kind'=>['required',Rule::in(['school','exam_body','exam_type'])],
   'name'=>'required|string|max:220','category'=>'nullable|string|max:80',
   'short_name'=>'nullable|string|max:80','state'=>'nullable|string|max:80',
   'country'=>'nullable|string|max:100','official_url'=>'nullable|url|max:500',
   'source_url'=>'nullable|url|max:500',
  ]);
  if($data['kind']==='school' && empty($data['category'])) return back()->withErrors(['category'=>'Choose a school category.']);
  $key=hash('sha256',$data['kind'].'|'.($data['category']??'').'|'.$data['name']);
  if(DB::table('education_reference_catalogue')->where('catalogue_key',$key)->exists()) return back()->withErrors(['name'=>'This reference already exists.']);
  $data['catalogue_key']=$key; $data['is_active']=true;
  $data['metadata']=json_encode(['catalogue_source'=>'admin_added']);
  $data['created_by']=$request->user()->id; $data['updated_by']=$request->user()->id;
  $data['created_at']=now(); $data['updated_at']=now();
  DB::table('education_reference_catalogue')->insert($data);
  return back()->with('success','Reference added to the catalogue.');
 }
 public function update(Request $request,int $id) {
  $entry=DB::table('education_reference_catalogue')->where('id',$id)->first();
  abort_unless($entry,404);
  $data=$request->validate([
   'name'=>'required|string|max:220','category'=>'nullable|string|max:80',
   'short_name'=>'nullable|string|max:80','state'=>'nullable|string|max:80',
   'country'=>'nullable|string|max:100','official_url'=>'nullable|url|max:500',
   'source_url'=>'nullable|url|max:500','is_active'=>'required|boolean',
  ]);
  $data['catalogue_key']=hash('sha256',$entry->kind.'|'.($data['category']??'').'|'.$data['name']);
  if(DB::table('education_reference_catalogue')->where('catalogue_key',$data['catalogue_key'])->where('id','!=',$id)->exists()) return back()->withErrors(['name'=>'Another reference already uses this name and category.']);
  $data['updated_by']=$request->user()->id; $data['updated_at']=now();
  DB::table('education_reference_catalogue')->where('id',$id)->update($data);
  return back()->with('success','Reference updated.');
 }
 public function destroy(int $id) {
  $entry=DB::table('education_reference_catalogue')->where('id',$id)->first(); abort_unless($entry,404);
  if($entry->kind==='school' && DB::table('education_library_items')->where('institution',$entry->name)->exists()) return back()->withErrors(['entry'=>'This school is used by past-question resources. Deactivate it instead.']);
  DB::table('education_reference_catalogue')->where('id',$id)->delete();
  return back()->with('success','Reference removed.');
 }
 public function storeCategory(Request $request) {
  $data=$request->validate(['kind'=>['required',Rule::in(['school','exam_body','exam_type'])],'name'=>'required|string|max:120','description'=>'nullable|string|max:1000','sort_order'=>'nullable|integer|min:0|max:999999']);
  $slug=Str::slug($data['name']); $exists=DB::table('education_reference_categories')->where('kind',$data['kind'])->where('slug',$slug)->exists();
  if($exists) return back()->withErrors(['category_name'=>'That category already exists for this reference type.']);
  DB::table('education_reference_categories')->insert(['kind'=>$data['kind'],'name'=>$data['name'],'slug'=>$slug,'description'=>$data['description']??null,'sort_order'=>$data['sort_order']??0,'is_active'=>true,'created_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);
  return back()->with('success','Reference category created.');
 }
 public function updateCategory(Request $request,int $id) {
  $data=$request->validate(['name'=>'required|string|max:120','description'=>'nullable|string|max:1000','sort_order'=>'nullable|integer|min:0|max:999999','is_active'=>'required|boolean']);
  $category=DB::table('education_reference_categories')->where('id',$id)->first(); abort_unless($category,404);
  $slug=Str::slug($data['name']);
  if($slug==='' ) $slug='category-'.$id;
  if(DB::table('education_reference_categories')->where('kind',$category->kind)->where('slug',$slug)->where('id','!=',$id)->exists()) return back()->withErrors(['category_name'=>'Another category already uses this name for this reference type.']);
  DB::table('education_reference_categories')->where('id',$id)->update(['name'=>$data['name'],'slug'=>$slug,'description'=>$data['description']??null,'sort_order'=>$data['sort_order']??0,'is_active'=>$data['is_active'],'updated_by'=>$request->user()->id,'updated_at'=>now()]);
  return back()->with('success','Reference category updated.');
 }
}
