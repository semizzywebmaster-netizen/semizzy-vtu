<?php
namespace Semizzy\Addons\Education\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Semizzy\Addons\Education\Models\EducationLibraryItem;
final class AdminEducationPastQuestionController extends Controller {
 public function index(Request $request) {
  $q=EducationLibraryItem::query()->withCount('purchases')->with('uploader')->latest();
  if($request->filled('category'))$q->where('category',$request->query('category'));
  if($request->filled('status'))$q->where('status',$request->query('status'));
  if($request->filled('q'))$q->where(function($s)use($request){$v=$request->query('q');$s->where('title','like','%'.$v.'%')->orWhere('institution','like','%'.$v.'%')->orWhere('exam_body','like','%'.$v.'%')->orWhere('subject','like','%'.$v.'%');});
  return Inertia::render('Admin/Education/PastQuestions',['items'=>$q->paginate(25)->withQueryString(),'filters'=>$request->only(['category','status','q'])]);
 }
 public function store(Request $request) {
  $data=$this->validateItem($request);
  $data['is_free']=(bool)$request->boolean('is_free');
  $file=$data['file']; $previewFile=$data['preview_file']??null; unset($data['file'],$data['preview_file']);
  $data['slug']=$this->uniqueSlug($data['title']);
  $data['file_path']=$file->store('education/past-questions','local');
  $data['file_name']=mb_substr(basename($file->getClientOriginalName()),0,255); $data['mime_type']=$file->getMimeType()?:'application/octet-stream'; $data['file_size']=$file->getSize()?:0;
  if($previewFile)$data['preview_path']=$previewFile->store('education/past-questions/previews','local');
  $data['uploaded_by']=$request->user()->id; $data['status']='draft'; $data['published_at']=null;
  if($data['is_free'])$data['price_minor']=0;
  EducationLibraryItem::create($data);
  return back()->with('success','Past-question resource uploaded as a draft. Review and publish it when ready.');
 }
 public function update(Request $request,EducationLibraryItem $item) {
  $data=$request->validate(['category'=>['required',Rule::in(['school_past_question','exam_past_question'])],'title'=>'required|string|max:200','institution'=>'nullable|string|max:180','faculty'=>'nullable|string|max:180','department'=>'nullable|string|max:180','course_code'=>'nullable|string|max:80','course_title'=>'nullable|string|max:180','education_level'=>'nullable|string|max:80','semester'=>'nullable|string|max:80','academic_session'=>'nullable|string|max:40','exam_body'=>'nullable|string|max:100','exam_type'=>'nullable|string|max:100','subject'=>'nullable|string|max:140','exam_year'=>'nullable|integer|min:1900|max:2100','description'=>'nullable|string|max:5000','is_free'=>'required|boolean','price_minor'=>'required|integer|min:0','currency'=>'required|string|size:3','file'=>'nullable|file|mimes:pdf,doc,docx|max:20480','preview_file'=>'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120']);
  $data['is_free']=(bool)$request->boolean('is_free');
  if($item->status==='published' && $item->category!==$data['category']) return back()->withErrors(['category'=>'Unpublish the resource before changing its section.']);
  if($data['is_free'])$data['price_minor']=0;
  $oldPath=null; $oldPreviewPath=null; $previewFile=$data['preview_file']??null; unset($data['preview_file']);
  if($previewFile) { $oldPreviewPath=$item->preview_path; $data['preview_path']=$previewFile->store('education/past-questions/previews','local'); }
  if(isset($data['file'])) { $file=$data['file'];$oldPath=$item->file_path;$newPath=$file->store('education/past-questions','local');$data['file_path']=$newPath;$data['file_name']=mb_substr(basename($file->getClientOriginalName()),0,255);$data['mime_type']=$file->getMimeType()?:'application/octet-stream';$data['file_size']=$file->getSize()?:0;unset($data['file']); }
  $item->fill($data)->save();
  if($oldPath && Storage::disk('local')->exists($oldPath))Storage::disk('local')->delete($oldPath);
  if($oldPreviewPath && Storage::disk('local')->exists($oldPreviewPath))Storage::disk('local')->delete($oldPreviewPath);
  return back()->with('success','Resource updated.');
 }
 public function publish(EducationLibraryItem $item) {
  if(!$item->is_free && $item->price_minor<1)return back()->withErrors(['price_minor'=>'Paid resources must have a price greater than zero.']);
  $item->update(['status'=>'published','published_at'=>now()]);
  return back()->with('success','Resource published.');
 }
 public function archive(EducationLibraryItem $item) {$item->update(['status'=>'archived']);return back()->with('success','Resource archived.');}
 public function destroy(EducationLibraryItem $item) {
  if($item->purchases()->exists())return back()->withErrors(['item'=>'This resource has purchase history. Archive it instead of deleting it.']);
  if(Storage::disk('local')->exists($item->file_path))Storage::disk('local')->delete($item->file_path);
  if($item->preview_path && Storage::disk('local')->exists($item->preview_path))Storage::disk('local')->delete($item->preview_path);
  $item->delete();return back()->with('success','Resource deleted.');
 }
 private function validateItem(Request $request):array {
  return $request->validate(['category'=>['required',Rule::in(['school_past_question','exam_past_question'])],'title'=>'required|string|max:200','institution'=>'nullable|string|max:180','faculty'=>'nullable|string|max:180','department'=>'nullable|string|max:180','course_code'=>'nullable|string|max:80','course_title'=>'nullable|string|max:180','education_level'=>'nullable|string|max:80','semester'=>'nullable|string|max:80','academic_session'=>'nullable|string|max:40','exam_body'=>'nullable|string|max:100','exam_type'=>'nullable|string|max:100','subject'=>'nullable|string|max:140','exam_year'=>'nullable|integer|min:1900|max:2100','description'=>'nullable|string|max:5000','is_free'=>'required|boolean','price_minor'=>'required|integer|min:0','currency'=>'required|string|size:3','file'=>'required|file|mimes:pdf,doc,docx|max:20480','preview_file'=>'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120']);
 }
 private function uniqueSlug(string $title):string {$base=Str::slug($title)?:'past-question';$slug=$base;$i=2;while(EducationLibraryItem::withTrashed()->where('slug',$slug)->exists())$slug=$base.'-'.$i++;return $slug;}
}
