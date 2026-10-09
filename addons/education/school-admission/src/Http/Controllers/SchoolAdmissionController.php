<?php

namespace Semizzy\\Addons\\SchoolAdmission\\Http\\Controllers;

use App\\Http\\Controllers\\Controller;
use Illuminate\\Http\\Request;
use Inertia\\Inertia;
use Inertia\\Response;
use Semizzy\\Addons\\SchoolAdmission\\Models\\SchoolAdmissionProduct;
use Semizzy\\Addons\\SchoolAdmission\\Models\\SchoolAdmissionTransaction;

final class SchoolAdmissionController extends Controller
{
    public function index(): Response
    {
        $products = SchoolAdmissionProduct::query()
            ->where('active', true)
            ->whereHas('institution', fn ($query) => $query->where('active', true))
            ->with(['institution:id,name,slug,institution_type,state,logo_path','programme:id,institution_id,name,level'])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('SchoolAdmission/Index', [
            'products' => $products,
            'serviceTypes' => ['application_form','post_utme','screening','acceptance_fee','admission_status','other'],
        ]);
    }

    public function show(Request $request, SchoolAdmissionTransaction $transaction): Response
    {
        abort_unless((int) $transaction->user_id === (int) $request->user()->id, 404);

        return Inertia::render('SchoolAdmission/Transaction', [
            'transaction' => $transaction->load('product.institution'),
        ]);
    }
}