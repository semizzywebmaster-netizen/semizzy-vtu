<?php
namespace Semizzy\Addons\Education\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Semizzy\Addons\Education\Models\EducationLibraryItem;
use Semizzy\Addons\Education\Models\EducationLibraryPurchase;
final class EducationPastQuestionController extends Controller {
 public function school(Request $request) { return $this->catalogue($request,'school_past_question','Education/SchoolPastQuestions','School Past Questions'); }
 public function exams(Request $request) { return $this->catalogue($request,'exam_past_question','Education/ExamPastQuestions','Exam Past Questions'); }
 private function catalogue(Request $request,string $category,string $page,string $title) {
  $q=EducationLibraryItem::query()->published()->category($category);
  $search=trim((string)$request->query('q',''));
  if($search!=='') $q->where(function($sub)use($search){$sub->where('title','like','%'.$search.'%')->orWhere('description','like','%'.$search.'%')->orWhere('institution','like','%'.$search.'%')->orWhere('course_title','like','%'.$search.'%')->orWhere('subject','like','%'.$search.'%')->orWhere('exam_body','like','%'.$search.'%');});
  foreach(['institution','department','course_code','education_level','semester','academic_session','exam_body','exam_type','subject','exam_year'] as $field) if($request->filled($field)) $q->where($field,$request->query($field));
  $items=$q->latest('published_at')->paginate(18)->withQueryString()->through(fn($item)=>[
   'id'=>$item->id,'title'=>$item->title,'slug'=>$item->slug,'description'=>$item->description,
   'institution'=>$item->institution,'faculty'=>$item->faculty,'department'=>$item->department,'course_code'=>$item->course_code,
   'course_title'=>$item->course_title,'education_level'=>$item->education_level,'semester'=>$item->semester,'academic_session'=>$item->academic_session,
   'exam_body'=>$item->exam_body,'exam_type'=>$item->exam_type,'subject'=>$item->subject,'exam_year'=>$item->exam_year,
   'is_free'=>$item->is_free,'price_minor'=>$item->price_minor,'currency'=>$item->currency,'file_size'=>$item->file_size,
   'owned'=>$item->is_free || EducationLibraryPurchase::where('user_id',$request->user()->id)->where('item_id',$item->id)->where('status','successful')->exists(),
  ]);
  return Inertia::render($page,['title'=>$title,'items'=>$items,'filters'=>$request->only(['q','institution','department','course_code','education_level','semester','academic_session','exam_body','exam_type','subject','exam_year'])]);
 }
 public function download(Request $request,EducationLibraryItem $item) {
  abort_unless($item->status==='published' && $item->published_at,404);
  $owns=$item->is_free || EducationLibraryPurchase::where('user_id',$request->user()->id)->where('item_id',$item->id)->where('status','successful')->exists();
  abort_unless($owns,403,'Purchase this resource before downloading.');
  abort_unless(Storage::disk('local')->exists($item->file_path),404,'The resource file is unavailable.');
  $item->increment('downloads_count');
  return Storage::disk('local')->download($item->file_path,$item->file_name,['Content-Type'=>$item->mime_type,'X-Content-Type-Options'=>'nosniff']);
 }
 public function purchase(Request $request,EducationLibraryItem $item) {
  abort_unless($item->status==='published' && $item->published_at,404);
  if($item->is_free) return back()->with('success','This resource is free. You can download it now.');
  if(EducationLibraryPurchase::where('user_id',$request->user()->id)->where('item_id',$item->id)->where('status','successful')->exists()) return back()->with('success','You already own this resource.');
  $wallet=\App\Models\WalletAccount::query()->where('user_id',$request->user()->id)->where('currency',$item->currency)->where('status','active')->orderBy('id')->first();
  if(!$wallet) return back()->withErrors(['wallet'=>'No active '.$item->currency.' wallet is available.']);
  try {
   \Illuminate\Support\Facades\DB::transaction(function()use($wallet,$item,$request){
    $locked=\App\Models\WalletAccount::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
    if($locked->user_id!==$request->user()->id || $locked->currency!==$item->currency || $locked->status!=='active') throw new \RuntimeException('The selected wallet is no longer eligible.');
    $again=EducationLibraryPurchase::where('user_id',$request->user()->id)->where('item_id',$item->id)->where('status','successful')->first();
    if($again) return;
    $amount=(string)$item->price_minor; $available=(string)$locked->available_minor; $held=(string)$locked->held_minor;
    if($this->compareMinor($available,$amount)<0) throw new \RuntimeException('Insufficient wallet balance.');
    $locked->available_minor=$this->subtractMinor($available,$amount); $locked->save();
    $reference='EDU-'.Str::upper(Str::random(20));
    \App\Models\WalletMovement::create(['wallet_account_id'=>$locked->id,'operation_key'=>'education-content:'.$request->user()->id.':'.$item->id,'reference'=>$reference,'type'=>'education_content_purchase','amount_minor'=>$amount,'currency'=>$locked->currency,'available_before_minor'=>$available,'available_after_minor'=>(string)$locked->available_minor,'held_before_minor'=>$held,'held_after_minor'=>$held,'metadata'=>['education_library_item_id'=>$item->id,'user_id'=>$request->user()->id]]);
    EducationLibraryPurchase::create(['user_id'=>$request->user()->id,'item_id'=>$item->id,'reference'=>$reference,'amount_minor'=>$item->price_minor,'currency'=>$item->currency,'status'=>'successful']);
   });
  } catch(\RuntimeException $e) { return back()->withErrors(['purchase'=>$e->getMessage()]); }
  return back()->with('success','Purchase complete. You can now download this resource.');
 }
 private function compareMinor(string $a,string $b):int {$a=ltrim($a,'0')?:'0';$b=ltrim($b,'0')?:'0';return strlen($a)<=>strlen($b) ?: strcmp($a,$b);}
 private function subtractMinor(string $a,string $b):string {if(function_exists('bcsub'))return bcsub($a,$b,0);return (string)((int)$a-(int)$b);}
}
