<?php
namespace Semizzy\Addons\Education\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Semizzy\Addons\Education\Models\{EducationInstitution,EducationAcademicUnit,EducationDepartment,EducationProgramme,EducationAcademicSession,EducationProgrammeAdmission,EducationAdmissionRoute,EducationAdmissionRequirement};

final class AdminSchoolAdmissionController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Education/SchoolAdmission', [
            'institutions'=>EducationInstitution::orderBy('name')->get(['id','name','category','state','active']),
            'units'=>EducationAcademicUnit::orderBy('name')->get(),
            'departments'=>EducationDepartment::orderBy('name')->get(),
            'programmes'=>EducationProgramme::orderBy('name')->get(),
            'sessions'=>EducationAcademicSession::orderByDesc('start_year')->get(),
            'admissionRoutes'=>EducationAdmissionRoute::where('active',true)->orderBy('name')->get(),
            'admissions'=>EducationProgrammeAdmission::with([
                'programme.institution','programme.academicUnit','programme.department','academicSession',
                'requirements.sources','requirements.route','cutoffs','screeningRules','sources'])->withCount('requirements')
            ])->latest()->paginate(25),
        ]);
    }

    public function unit(Request $r)
    {
        $d=$r->validate(['institution_id'=>'required|exists:education_institutions,id','parent_unit_id'=>'nullable|exists:education_academic_units,id','unit_type'=>'required|string|max:40','name'=>'required|string|max:190','short_name'=>'nullable|string|max:190','code'=>'nullable|string|max:80','source_url'=>'nullable|url','source_title'=>'nullable|string|max:190']);
        if(isset($d['parent_unit_id']) && !EducationAcademicUnit::whereKey($d['parent_unit_id'])->where('institution_id',$d['institution_id'])->exists()) return back()->withErrors(['parent_unit_id'=>'Parent academic unit must belong to the selected institution.']);
        $d['slug']=Str::slug($d['name']); EducationAcademicUnit::create($d);
        return back()->with('success','Academic unit created.');
    }

    public function department(Request $r)
    {
        $d=$r->validate(['institution_id'=>'required|exists:education_institutions,id','academic_unit_id'=>'nullable|exists:education_academic_units,id','name'=>'required|string|max:190','short_name'=>'nullable|string|max:190','code'=>'nullable|string|max:80','source_url'=>'nullable|url','source_title'=>'nullable|string|max:190']);
        if(isset($d['academic_unit_id']) && !EducationAcademicUnit::whereKey($d['academic_unit_id'])->where('institution_id',$d['institution_id'])->exists()) return back()->withErrors(['academic_unit_id'=>'Academic unit must belong to the selected institution.']);
        $d['slug']=Str::slug($d['name']); EducationDepartment::create($d);
        return back()->with('success','Department created.');
    }

    public function programme(Request $r)
    {
        $d=$r->validate(['institution_id'=>'required|exists:education_institutions,id','academic_unit_id'=>'nullable|exists:education_academic_units,id','department_id'=>'nullable|exists:education_departments,id','name'=>'required|string|max:190','official_title'=>'nullable|string|max:190','award_type'=>'nullable|string|max:60','qualification_level'=>'nullable|string|max:60','duration_value'=>'nullable|integer|min:1|max:20','duration_unit'=>'nullable|string|max:20','study_mode'=>'nullable|string|max:40','source_url'=>'nullable|url','source_title'=>'nullable|string|max:190']);
        if(isset($d['academic_unit_id']) && !EducationAcademicUnit::whereKey($d['academic_unit_id'])->where('institution_id',$d['institution_id'])->exists()) return back()->withErrors(['academic_unit_id'=>'Academic unit must belong to the selected institution.']);
        if(isset($d['department_id']) && !EducationDepartment::whereKey($d['department_id'])->where('institution_id',$d['institution_id'])->exists()) return back()->withErrors(['department_id'=>'Department must belong to the selected institution.']);
        if(isset($d['department_id'], $d['academic_unit_id']) && $d['academic_unit_id'] && !EducationDepartment::whereKey($d['department_id'])->where('academic_unit_id',$d['academic_unit_id'])->exists()) return back()->withErrors(['department_id'=>'Department must belong to the selected academic unit.']);
        $d['slug']=Str::slug($d['name']); EducationProgramme::create($d);
        return back()->with('success','Programme created.');
    }

    public function session(Request $r)
    {
        $d=$r->validate(['name'=>'required|string|max:30','start_year'=>'required|integer|min:2000|max:2100','end_year'=>'required|integer|min:2000|max:2100']);
        abort_if($d['end_year']<$d['start_year'],422,'End year cannot be before start year.');
        $d['slug']=Str::slug($d['name']); $d['status']='active'; EducationAcademicSession::create($d);
        return back()->with('success','Academic session created.');
    }

    public function admission(Request $r)
    {
        $d=$r->validate(['programme_id'=>'required|exists:education_programmes,id','academic_session_id'=>'required|exists:education_academic_sessions,id','utme_available'=>'boolean','direct_entry_available'=>'boolean','other_entry_available'=>'boolean','first_choice_required'=>'nullable|boolean','general_notes'=>'nullable|string','official_source_url'=>'required|url','source_title'=>'required|string|max:190','source_published_at'=>'nullable|date','effective_from'=>'nullable|date','effective_until'=>'nullable|date']);
        abort_if(isset($d['effective_until'],$d['effective_from']) && $d['effective_until']<$d['effective_from'],422,'Effective end date cannot be before effective start date.');
        $admission=EducationProgrammeAdmission::create(array_merge($d,['verification_status'=>'researched','admission_status'=>'researched']));
        $admission->sources()->create(['source_type'=>'official_admission_document','title'=>$d['source_title'],'url'=>$d['official_source_url'],'publication_date'=>$d['source_published_at']??null,'retrieved_at'=>now(),'effective_from'=>$d['effective_from']??null,'effective_until'=>$d['effective_until']??null,'verification_status'=>'researched']);
        return back()->with('success','Programme admission record created for research/verification.');
    }

    public function requirement(Request $r)
    {
        $d=$r->validate([
            'programme_admission_id'=>'required|exists:education_programme_admissions,id',
            'admission_route_id'=>'nullable|exists:education_admission_routes,id',
            'candidate_type'=>'nullable|string|max:80','minimum_age'=>'nullable|integer|min:0|max:100','maximum_age'=>'nullable|integer|min:0|max:100',
            'age_reference_date'=>'nullable|date','minimum_utme_score'=>'nullable|integer|min:0|max:1000','minimum_o_level_credit_count'=>'nullable|integer|min:0|max:20',
            'maximum_o_level_sittings'=>'nullable|integer|min:1|max:5','awaiting_result_allowed'=>'nullable|boolean','post_utme_required'=>'nullable|boolean',
            'post_utme_minimum_score'=>'nullable|integer|min:0|max:1000','screening_required'=>'nullable|boolean','notes'=>'nullable|string','special_conditions'=>'nullable|string',
            'source_url'=>'required|url','source_title'=>'required|string|max:190','source_published_at'=>'nullable|date',
            'olevel.minimum_credit_count'=>'nullable|integer|min:1|max:20','olevel.maximum_sittings'=>'nullable|integer|min:1|max:5','olevel.awaiting_result_allowed'=>'nullable|boolean',
            'olevel.required_english'=>'nullable|boolean','olevel.required_mathematics'=>'nullable|boolean','olevel.notes'=>'nullable|string',
            'olevel_subjects'=>'nullable|string','utme_subjects'=>'nullable|string','direct_entry_qualifications'=>'nullable|string','result_bodies'=>'nullable|string'
        ]);
        $admission=EducationProgrammeAdmission::findOrFail($d['programme_admission_id']);
        if($d['admission_route_id'] ?? null) abort_unless(EducationAdmissionRoute::whereKey($d['admission_route_id'])->where('active',true)->exists(),422,'Selected admission route is inactive.');
        abort_if(isset($d['maximum_age'],$d['minimum_age']) && $d['minimum_age']>$d['maximum_age'],422,'Maximum age cannot be below minimum age.');
        $req=null;
        DB::transaction(function() use ($d,$admission,&$req) {
            $payload=$d; foreach(['olevel','olevel_subjects','utme_subjects','direct_entry_qualifications','result_bodies'] as $key) unset($payload[$key]);
            $payload['status']='researched'; $payload['verification_status']='researched';
            $req=$admission->requirements()->create($payload);
            if(($d['olevel.minimum_credit_count'] ?? null)!==null){
                $o=$req->olevel()->create([
                    'minimum_credit_count'=>$d['olevel']['minimum_credit_count'],'maximum_sittings'=>$d['olevel']['maximum_sittings']??null,
                    'awaiting_result_allowed'=>$d['olevel']['awaiting_result_allowed']??null,'required_english'=>(bool)($d['olevel']['required_english']??false),
                    'required_mathematics'=>$d['olevel']['required_mathematics']??null,'notes'=>$d['olevel']['notes']??null
                ]);
                foreach($this->csv($d['olevel_subjects']??'') as $i=>$subject) $o->subjects()->create(['subject_name'=>$subject,'compulsory'=>true,'display_order'=>$i]);
            }
            $utme=$this->csv($d['utme_subjects']??'');
            if($utme){ $combo=$req->utmeCombinations()->create(['name'=>'Researched UTME combination','minimum_subject_count'=>count($utme)]); foreach($utme as $i=>$subject) $combo->subjects()->create(['subject_name'=>$subject,'compulsory'=>true,'display_order'=>$i]); }
            foreach($this->csv($d['direct_entry_qualifications']??'') as $q) $req->directEntryQualifications()->create(['qualification_type'=>$q]);
            foreach($this->csv($d['result_bodies']??'') as $body) $req->resultBodies()->create(['body_name'=>$body,'accepted'=>true]);
            $req->sources()->create(['programme_admission_id'=>$admission->id,'admission_requirement_id'=>$req->id,'source_type'=>'official_admission_document','title'=>$d['source_title'],'url'=>$d['source_url'],'publication_date'=>$d['source_published_at']??null,'retrieved_at'=>now(),'verification_status'=>'researched']);
        });
        return back()->with('success','Structured admission requirements saved for research/review.');
    }

    private function csv(string $value): array { return array_values(array_unique(array_filter(array_map(fn($x)=>trim($x),preg_split('/[,\\n]+/',$value))))); }

    public function cutoff(Request $r)
    {
        $d=$r->validate(['programme_admission_id'=>'required|exists:education_programme_admissions,id','cutoff_type'=>'required|string|max:40','score'=>'required|numeric|min:0','score_scale'=>'nullable|numeric|min:0','candidate_category'=>'nullable|string|max:80','notes'=>'nullable|string','source_url'=>'required|url','source_title'=>'required|string|max:190','source_published_at'=>'nullable|date']);
        \Semizzy\Addons\Education\Models\EducationAdmissionCutoff::create($d);
        return back()->with('success','Cut-off saved for research/review.');
    }

    public function screening(Request $r)
    {
        $d=$r->validate(['programme_admission_id'=>'required|exists:education_programme_admissions,id','required'=>'boolean','screening_type'=>'required|string|max:60','minimum_score'=>'nullable|numeric|min:0','registration_required'=>'boolean','first_choice_required'=>'nullable|boolean','result_upload_required'=>'nullable|boolean','screening_url'=>'nullable|url','start_date'=>'nullable|date','end_date'=>'nullable|date','notes'=>'nullable|string','source_url'=>'required|url','source_title'=>'required|string|max:190','source_published_at'=>'nullable|date']);
        abort_if(isset($d['start_date'],$d['end_date']) && $d['end_date']<$d['start_date'],422,'Screening end date cannot be before start date.');
        \Semizzy\Addons\Education\Models\EducationAdmissionScreeningRule::create($d);
        return back()->with('success','Screening rule saved for research/review.');
    }

    public function verifyRequirement(EducationAdmissionRequirement $requirement)
    {
        $requirement->load('sources');
        abort_unless($requirement->sources->isNotEmpty() && $requirement->source_url,422,'Requirement source provenance is required before verification.');
        $requirement->update(['status'=>'verified','verification_status'=>'verified','verified_at'=>now()]);
        return back()->with('success','Admission requirement verified.');
    }

    public function verify(EducationProgrammeAdmission $admission)
    {
        $admission->load(['sources','requirements.sources']);
        abort_unless($admission->official_source_url && $admission->source_title,422,'Official admission source is required before verification.');
        abort_if($admission->requirements->isEmpty(),422,'At least one structured admission requirement is required before verification.');
        if($admission->requirements->contains(fn($r)=>$r->status==='draft' || $r->verification_status==='draft' || $r->sources->isEmpty())) abort(422,'Every admission requirement must be researched and have source provenance before verification.');
        $admission->update(['verification_status'=>'verified','admission_status'=>'verified','verified_at'=>now()]);
        return back()->with('success','Admission record verified.');
    }

    public function publish(EducationProgrammeAdmission $admission)
    {
        $admission->load(['sources','requirements.sources','cutoffs','screeningRules']);
        abort_unless($admission->verification_status==='verified',422,'Only verified admission records can be published.');
        abort_unless($admission->official_source_url && $admission->source_title,422,'Official admission source is required before publication.');
        if($admission->requirements->isEmpty() || $admission->requirements->contains(fn($r)=>$r->verification_status!=='verified' || $r->sources->isEmpty())) abort(422,'Every admission requirement must be verified and have source provenance before publication.');
        if($admission->cutoffs->contains(fn($x)=>empty($x->source_url)) || $admission->screeningRules->contains(fn($x)=>empty($x->source_url))) abort(422,'Every cut-off and screening rule must have source provenance before publication.');
        $admission->update(['verification_status'=>'published','admission_status'=>'published','published_at'=>now()]);
        return back()->with('success','Admission record published.');
    }

    public function archive(EducationProgrammeAdmission $admission)
    {
        $admission->update(['verification_status'=>'archived','admission_status'=>'archived']);
        return back()->with('success','Admission record archived.');
    }

    public function seedRoutes()
    {
        foreach([['code'=>'utme','name'=>'UTME'],['code'=>'direct_entry','name'=>'Direct Entry']] as $x) EducationAdmissionRoute::firstOrCreate(['code'=>$x['code']],$x);
        return back()->with('success','Admission routes synchronized.');
    }
}
