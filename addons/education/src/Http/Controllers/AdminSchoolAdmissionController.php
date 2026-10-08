<?php
namespace Semizzy\Addons\Education\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Semizzy\Addons\Education\Models\{EducationInstitution,EducationAcademicUnit,EducationDepartment,EducationProgramme,EducationAcademicSession,EducationProgrammeAdmission,EducationAdmissionRoute};
final class AdminSchoolAdmissionController extends Controller {
 public function index(){return Inertia::render('Admin/Education/SchoolAdmission',['institutions'=>EducationInstitution::orderBy('name')->get(['id','name','category','state','active']),'units'=>EducationAcademicUnit::with('institution')->orderBy('name')->get(),'departments'=>EducationDepartment::with(['institution','academicUnit'])->orderBy('name')->get(),'programmes'=>EducationProgramme::with(['institution','academicUnit','department'])->orderBy('name')->get(),'sessions'=>EducationAcademicSession::orderByDesc('start_year')->get(),'admissionRoutes'=>EducationAdmissionRoute::where('active',true)->get(),'admissions'=>EducationProgrammeAdmission::with(['programme','academicSession'])->latest()->paginate(25)]);}
 public function unit(Request $r){$d=$r->validate(['institution_id'=>'required|exists:education_institutions,id','parent_unit_id'=>'nullable|exists:education_academic_units,id','unit_type'=>'required|string|max:40','name'=>'required|string|max:190','short_name'=>'nullable|string|max:190','code'=>'nullable|string|max:80','source_url'=>'nullable|url']);$d['slug']=Str::slug($d['name']);return back()->with('success',EducationAcademicUnit::create($d)?'Academic unit created.':'Unable to create academic unit.');}
 public function department(Request $r){$d=$r->validate(['institution_id'=>'required|exists:education_institutions,id','academic_unit_id'=>'nullable|exists:education_academic_units,id','name'=>'required|string|max:190','short_name'=>'nullable|string|max:190','code'=>'nullable|string|max:80','source_url'=>'nullable|url']);$d['slug']=Str::slug($d['name']);return back()->with('success',EducationDepartment::create($d)?'Department created.':'Unable to create department.');}
 public function programme(Request $r){$d=$r->validate(['institution_id'=>'required|exists:education_institutions,id','academic_unit_id'=>'nullable|exists:education_academic_units,id','department_id'=>'nullable|exists:education_departments,id','name'=>'required|string|max:190','official_title'=>'nullable|string|max:190','award_type'=>'nullable|string|max:60','qualification_level'=>'nullable|string|max:60','duration_value'=>'nullable|integer|min:1|max:20','duration_unit'=>'nullable|string|max:20','study_mode'=>'nullable|string|max:40','source_url'=>'nullable|url']);$d['slug']=Str::slug($d['name']);return back()->with('success',EducationProgramme::create($d)?'Programme created.':'Unable to create programme.');}
 public function session(Request $r){$d=$r->validate(['name'=>'required|string|max:30','start_year'=>'required|integer|min:2000|max:2100','end_year'=>'required|integer|min:2000|max:2100']);$d['slug']=Str::slug($d['name']);$d['status']='active';return back()->with('success',EducationAcademicSession::create($d)?'Academic session created.':'Unable to create session.');}
 public function admission(Request $r){$d=$r->validate(['programme_id'=>'required|exists:education_programmes,id','academic_session_id'=>'required|exists:education_academic_sessions,id','utme_available'=>'boolean','direct_entry_available'=>'boolean','other_entry_available'=>'boolean','first_choice_required'=>'nullable|boolean','general_notes'=>'nullable|string','official_source_url'=>'required|url','source_title'=>'required|string|max:190']);$d['verification_status']='researched';$d['admission_status']='researched';return back()->with('success',EducationProgrammeAdmission::create($d)?'Programme admission record created for research/verification.':'Unable to create admission record.');}
 public function requirement(Request $r){
  $d=$r->validate(['programme_admission_id'=>'required|exists:education_programme_admissions,id','admission_route_id'=>'nullable|exists:education_admission_routes,id','candidate_type'=>'nullable|string|max:80','minimum_age'=>'nullable|integer|min:0|max:100','maximum_age'=>'nullable|integer|min:0|max:100','age_reference_date'=>'nullable|date','minimum_utme_score'=>'nullable|integer|min:0|max:1000','minimum_o_level_credit_count'=>'nullable|integer|min:0|max:20','maximum_o_level_sittings'=>'nullable|integer|min:1|max:5','awaiting_result_allowed'=>'nullable|boolean','post_utme_required'=>'nullable|boolean','post_utme_minimum_score'=>'nullable|integer|min:0|max:1000','screening_required'=>'nullable|boolean','notes'=>'nullable|string','special_conditions'=>'nullable|string','source_url'=>'required|url','source_title'=>'required|string|max:190']);
  $d['verification_status']='researched'; $d['status']='researched';
  $req=\\Semizzy\\Addons\\Education\\Models\\EducationAdmissionRequirement::create($d);
  if($r->filled('olevel')){ $o=$r->validate(['olevel.minimum_credit_count'=>'required|integer|min:1|max:20','olevel.maximum_sittings'=>'nullable|integer|min:1|max:5','olevel.awaiting_result_allowed'=>'nullable|boolean','olevel.required_english'=>'boolean','olevel.required_mathematics'=>'nullable|boolean','olevel.notes'=>'nullable|string']); $o=$o['olevel']; $ol=\\Semizzy\\Addons\\Education\\Models\\EducationAdmissionOLevelRequirement::create(array_merge($o,['admission_requirement_id'=>$req->id])); foreach((array)$r->input('olevel.subjects',[]) as $s){$ol->subjects()->create($s);} }
  foreach((array)$r->input('utme_combinations',[]) as $combo){$m=$req->utmeCombinations()->create(['name'=>$combo['name']??'UTME combination','minimum_subject_count'=>$combo['minimum_subject_count']??null,'notes'=>$combo['notes']??null]); foreach((array)($combo['subjects']??[]) as $s){$m->subjects()->create($s);} }
  foreach((array)$r->input('direct_entry_qualifications',[]) as $q){$req->directEntryQualifications()->create($q);}
  foreach((array)$r->input('result_bodies',[]) as $b){$req->resultBodies()->create($b);}
  $req->sources()->create(['programme_admission_id'=>$req->programme_admission_id,'admission_requirement_id'=>$req->id,'source_type'=>'official_admission_document','title'=>$d['source_title'],'url'=>$d['source_url'],'publisher'=>$r->input('publisher'),'publication_date'=>$r->input('source_published_at'),'retrieved_at'=>now(),'verification_status'=>'researched']);
  return back()->with('success','Structured admission requirements saved for research/review.');
 }
 public function cutoff(Request $r){$d=$r->validate(['programme_admission_id'=>'required|exists:education_programme_admissions,id','cutoff_type'=>'required|string|max:40','score'=>'required|numeric|min:0','score_scale'=>'nullable|numeric|min:0','candidate_category'=>'nullable|string|max:80','notes'=>'nullable|string','source_url'=>'required|url','source_title'=>'required|string|max:190']); $d['verified_at']=null; \\Semizzy\\Addons\\Education\\Models\\EducationAdmissionCutoff::create($d); return back()->with('success','Cut-off saved for research/review.');}
 public function screening(Request $r){$d=$r->validate(['programme_admission_id'=>'required|exists:education_programme_admissions,id','required'=>'boolean','screening_type'=>'required|string|max:60','minimum_score'=>'nullable|numeric|min:0','registration_required'=>'boolean','first_choice_required'=>'nullable|boolean','result_upload_required'=>'nullable|boolean','screening_url'=>'nullable|url','start_date'=>'nullable|date','end_date'=>'nullable|date','notes'=>'nullable|string','source_url'=>'required|url','source_title'=>'required|string|max:190']); \\Semizzy\\Addons\\Education\\Models\\EducationAdmissionScreeningRule::create($d); return back()->with('success','Screening rule saved for research/review.');}
 public function verifyRequirement(\Semizzy\Addons\Education\Models\EducationAdmissionRequirement $requirement){
  $requirement->load('sources');
  abort_unless($requirement->sources->isNotEmpty() && $requirement->source_url,422,'Requirement source provenance is required before verification.');
  $requirement->update(['status'=>'verified','verification_status'=>'verified','verified_at'=>now()]);
  return back()->with('success','Admission requirement verified.');
 }
 public function verify(EducationProgrammeAdmission $admission){
  $admission->load(['sources','requirements.sources']);
  abort_unless($admission->official_source_url && $admission->source_title,422,'Official admission source is required before verification.');
  if($admission->requirements->contains(fn($r)=>$r->status==='draft' || $r->verification_status==='draft' || $r->sources->isEmpty())){
   abort(422,'Every admission requirement must be researched and have source provenance before verification.');
  }
  $admission->update(['verification_status'=>'verified','admission_status'=>'verified','verified_at'=>now()]);
  return back()->with('success','Admission record verified.');
 }
 public function publish(EducationProgrammeAdmission $admission){
  $admission->load(['sources','requirements.sources']);
  abort_unless($admission->verification_status==='verified',422,'Only verified admission records can be published.');
  abort_unless($admission->official_source_url && $admission->source_title,422,'Official admission source is required before publication.');
  if($admission->requirements->contains(fn($r)=>$r->verification_status!=='verified' || $r->sources->isEmpty())){
   abort(422,'Every admission requirement must be verified and have source provenance before publication.');
  }
  $admission->update(['verification_status'=>'published','admission_status'=>'published','published_at'=>now()]);
  return back()->with('success','Admission record published.');
 }
 public function archive(EducationProgrammeAdmission $admission){
  $admission->update(['verification_status'=>'archived','admission_status'=>'archived']);
  return back()->with('success','Admission record archived.');
 }
 public function seedRoutes(){foreach([['code'=>'utme','name'=>'UTME'],['code'=>'direct_entry','name'=>'Direct Entry']] as $x) EducationAdmissionRoute::firstOrCreate(['code'=>$x['code']],$x);return back()->with('success','Admission routes synchronized.');}
}