<?php
namespace Semizzy\Addons\Education\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Semizzy\Addons\Education\Models\{EducationInstitution,EducationAcademicSession,EducationAcademicUnit,EducationDepartment,EducationProgramme,EducationProgrammeAdmission};
final class SchoolAdmissionController extends Controller {
 public function index(Request $request){$institutions=EducationInstitution::where('active',true)->orderBy('name')->get(['id','name','category','state']);$sessions=EducationAcademicSession::orderByDesc('start_year')->get(['id','name','is_current']);$admissions=EducationProgrammeAdmission::with(['programme.institution','programme.academicUnit','programme.department','academicSession'])->where('verification_status','published')->latest()->paginate(24)->withQueryString();return Inertia::render('Education/SchoolAdmission',['institutions'=>$institutions,'sessions'=>$sessions,'admissions'=>$admissions]);}
 public function show(EducationProgrammeAdmission $admission){$admission->load(['programme.institution','programme.academicUnit','programme.department','academicSession','routes','requirements.olevel.subjects','requirements.utmeCombinations.subjects','requirements.directEntryQualifications','requirements.resultBodies','cutoffs','screeningRules','specialRules','documents','sources']);return Inertia::render('Education/SchoolAdmissionDetail',['admission'=>$admission]);}
 public function lookup(Request $request){$q=trim((string)$request->get('q',''));return response()->json(EducationProgramme::with(['institution','academicUnit','department'])->when($q,fn($x)=>$x->where('name','like',"%{$q}%"))->where('active',true)->limit(50)->get());}
}