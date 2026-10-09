<?php

namespace Semizzy\Addons\SchoolAdmission\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Semizzy\Addons\SchoolAdmission\Models\SchoolAdmissionProduct;
use Semizzy\Addons\SchoolAdmission\Models\SchoolAdmissionTransaction;

final class SchoolAdmissionController extends Controller
{
    public function index(): Response
    {
        $products = SchoolAdmissionProduct::query()
            ->where('active', true)
            ->where(function ($query): void {
                $query->whereNull('institution_id')->orWhereHas('institution', fn ($institution) => $institution->where('active', true));
            })
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

        $transaction->load(['product:id,name,service_type,institution_id','product.institution:id,name']);

        return Inertia::render('SchoolAdmission/Transaction', [
            'transaction' => [
                'reference' => $transaction->reference,
                'status' => $transaction->status,
                'amount_minor' => $transaction->amount_minor,
                'currency' => $transaction->currency,
                'candidate_identifier' => $transaction->candidate_identifier,
                'provider_reference' => $transaction->provider_reference,
                'error' => $transaction->error,
                'created_at' => $transaction->created_at,
                'product' => $transaction->product ? [
                    'name' => $transaction->product->name,
                    'service_type' => $transaction->product->service_type,
                    'institution' => $transaction->product->institution ? ['name' => $transaction->product->institution->name] : null,
                ] : null,
            ],
        ]);
    }
}