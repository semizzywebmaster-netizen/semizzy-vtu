<?php

namespace Semizzy\Addons\SchoolAdmission\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Semizzy\Addons\SchoolAdmission\Models\SchoolAdmissionInstitution;
use Semizzy\Addons\SchoolAdmission\Models\SchoolAdmissionProduct;
use Semizzy\Addons\SchoolAdmission\Models\SchoolAdmissionProgramme;
use Semizzy\Addons\SchoolAdmission\Models\SchoolAdmissionTransaction;

final class AdminSchoolAdmissionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/SchoolAdmission/Index', [
            'institutions' => SchoolAdmissionInstitution::withCount(['programmes','products'])->orderBy('name')->get(),
            'programmes' => SchoolAdmissionProgramme::with('institution:id,name')->orderBy('name')->get(),
            'products' => SchoolAdmissionProduct::with(['institution:id,name','programme:id,name','provider:id,identifier,display_name'])->withCount('transactions')->orderBy('display_order')->orderBy('name')->get(),
            'providers' => ApiProvider::query()->orderBy('display_name')->get(['id','identifier','display_name']),
            'transactions' => SchoolAdmissionTransaction::with(['product:id,name','user:id,name,email'])->latest()->paginate(25),
            'serviceTypes' => ['application_form','post_utme','screening','acceptance_fee','admission_status','other'],
            'institutionTypes' => ['university','polytechnic','college_of_education','technical_college','other'],
        ]);
    }

    public function storeInstitution(Request $request)
    {
        $data = $request->validate([
            'slug' => ['required','string','max:120','regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/','unique:school_admission_institutions,slug'],
            'name' => ['required','string','max:200'],
            'institution_type' => ['required',Rule::in(['university','polytechnic','college_of_education','technical_college','other'])],
            'country_code' => ['required','string','size:2'],
            'state' => ['nullable','string','max:100'],
            'official_website' => ['nullable','url','max:255'],
            'admissions_url' => ['nullable','url','max:255'],
            'active' => ['sometimes','boolean'],
        ]);
        $data['slug'] = strtolower($data['slug']);
        $data['country_code'] = strtoupper($data['country_code']);
        $data['active'] = (bool) ($data['active'] ?? true);
        SchoolAdmissionInstitution::create($data);

        return back()->with('success','Institution created.');
    }

    public function updateInstitution(Request $request, SchoolAdmissionInstitution $institution)
    {
        $data = $request->validate([
            'slug' => ['required','string','max:120','regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',Rule::unique('school_admission_institutions','slug')->ignore($institution->id)],
            'name' => ['required','string','max:200'],
            'institution_type' => ['required',Rule::in(['university','polytechnic','college_of_education','technical_college','other'])],
            'country_code' => ['required','string','size:2'],
            'state' => ['nullable','string','max:100'],
            'official_website' => ['nullable','url','max:255'],
            'admissions_url' => ['nullable','url','max:255'],
            'active' => ['required','boolean'],
        ]);
        $data['slug'] = strtolower($data['slug']);
        $data['country_code'] = strtoupper($data['country_code']);
        $institution->update($data);

        return back()->with('success','Institution updated.');
    }

    public function storeProduct(Request $request)
    {
        $data = $request->validate([
            'key' => ['required','string','max:120','regex:/^[a-z0-9][a-z0-9._-]*$/','unique:school_admission_products,key'],
            'institution_id' => ['nullable','integer','exists:school_admission_institutions,id'],
            'programme_id' => ['nullable','integer','exists:school_admission_programmes,id'],
            'service_type' => ['required',Rule::in(['application_form','post_utme','screening','acceptance_fee','admission_status','other'])],
            'name' => ['required','string','max:200'],
            'description' => ['nullable','string','max:5000'],
            'currency' => ['required','string','size:3'],
            'price_minor' => ['required','integer','min:0'],
            'provider_id' => ['nullable','integer','exists:api_providers,id'],
            'provider_product_code' => ['nullable','string','max:160'],
            'active' => ['sometimes','boolean'],
            'display_order' => ['sometimes','integer','min:0','max:100000'],
            'requirements' => ['nullable','array'],
        ]);
        $this->synchronizeProgrammeInstitution($data);
        $data['key'] = strtolower($data['key']);
        $data['currency'] = strtoupper($data['currency']);
        $data['active'] = (bool) ($data['active'] ?? true);
        $data['display_order'] = (int) ($data['display_order'] ?? 100);
        SchoolAdmissionProduct::create($data);

        return back()->with('success','Admission product created.');
    }

    public function updateProduct(Request $request, SchoolAdmissionProduct $product)
    {
        $data = $request->validate([
            'key' => ['required','string','max:120','regex:/^[a-z0-9][a-z0-9._-]*$/',Rule::unique('school_admission_products','key')->ignore($product->id)],
            'institution_id' => ['nullable','integer','exists:school_admission_institutions,id'],
            'programme_id' => ['nullable','integer','exists:school_admission_programmes,id'],
            'service_type' => ['required',Rule::in(['application_form','post_utme','screening','acceptance_fee','admission_status','other'])],
            'name' => ['required','string','max:200'],
            'description' => ['nullable','string','max:5000'],
            'currency' => ['required','string','size:3'],
            'price_minor' => ['required','integer','min:0'],
            'provider_id' => ['nullable','integer','exists:api_providers,id'],
            'provider_product_code' => ['nullable','string','max:160'],
            'active' => ['required','boolean'],
            'display_order' => ['required','integer','min:0','max:100000'],
            'requirements' => ['nullable','array'],
        ]);
        $this->synchronizeProgrammeInstitution($data);
        $data['key'] = strtolower($data['key']);
        $data['currency'] = strtoupper($data['currency']);
        $product->update($data);

        return back()->with('success','Admission product updated.');
    }

    private function synchronizeProgrammeInstitution(array &$data): void
    {
        if (empty($data['programme_id'])) return;

        $programme = SchoolAdmissionProgramme::query()->findOrFail($data['programme_id']);
        if (!empty($data['institution_id']) && (int) $data['institution_id'] !== (int) $programme->institution_id) {
            abort(422, 'Programme does not belong to the selected institution.');
        }

        $data['institution_id'] = $programme->institution_id;
    }

    public function toggleProduct(SchoolAdmissionProduct $product)
    {
        $product->update(['active' => !$product->active]);

        return back()->with('success',$product->active ? 'Admission product enabled.' : 'Admission product disabled.');
    }
}