<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use App\Services\KycLookupBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class KycController extends Controller
{
    public function index(Request $request): Response
    {
        $user=$request->user();
        return Inertia::render('Kyc', ['kyc'=>[
            'status'=>$user->kyc_status ?? 'not_started','identityType'=>$user->identity_type,
            'identityNumber'=>$user->identity_number ? '••••••••'.substr((string)$user->identity_number,-4) : null,
            'documentUrl'=>$user->identity_document_path ? route('kyc.document') : null,
            'submittedAt'=>$user->kyc_submitted_at?->toISOString(),'reviewedAt'=>$user->kyc_reviewed_at?->toISOString(),
            'rejectionReason'=>$user->kyc_rejection_reason,
        ]]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $user=$request->user();
        if (($user->kyc_status ?? 'not_started') === 'approved') return back()->with('success','Your KYC is already approved.');
        $data=$request->validate([
            'identity_type'=>['required',Rule::in(['nin','bvn','passport','drivers_license','voters_card','other'])],
            'identity_number'=>['required','string','min:4','max:120'],
            'identity_document'=>['required','file','mimes:jpg,jpeg,png,pdf,webp','max:10240'],
        ]);
        if(filled($user->identity_number) && trim((string)$user->identity_number)!==trim((string)$data['identity_number'])) return back()->withErrors(['identity_number'=>'Your saved identity number is confidential and cannot be changed.'])->withInput();
        $path=$request->file('identity_document')->store('users/identity-documents','local');
        $old=$user->identity_document_path;
        $user->forceFill([
            'identity_type'=>$data['identity_type'],'identity_number'=>$user->identity_number ?: trim($data['identity_number']),
            'identity_document_path'=>$path,'kyc_status'=>'pending','kyc_submitted_at'=>now(),'kyc_reviewed_at'=>null,'kyc_rejection_reason'=>null,
        ])->saveOrFail();
        if($old) Storage::disk('local')->delete($old);
        return back()->with('success','KYC application submitted for review.');
    }

    public function lookup(Request $request, KycLookupBillingService $billing): mixed
    {
        $data = $request->validate([
            'identity_type' => ['required', Rule::in(['nin', 'bvn'])],
            'identity_number' => ['required', 'string', 'min:8', 'max:40'],
        ]);

        try {
            $result = $billing->lookup(
                $request->user(),
                $data['identity_type'],
                $data['identity_number']
            );

            return back()->with('success', strtoupper($data['identity_type']).' verification lookup completed.')->with('kycLookup', $result);
        } catch (\\Throwable $e) {
            return back()->withErrors(['identity_number' => $e->getMessage()])->withInput();
        }
    }

    public function document(Request $request): mixed
    {
        $user=$request->user(); abort_unless($user->identity_document_path && Storage::disk('local')->exists($user->identity_document_path),404);
        return response()->download(Storage::disk('local')->path($user->identity_document_path),'identity-document');
    }
}
