<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProfileChangeRequest;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\BinaryFileResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileChangeRequestController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/ProfileChangeRequests', [
            'requests' => ProfileChangeRequest::with('user:id,name,username,email,phone,tier')
                ->latest()
                ->paginate(25)
                ->through(fn (ProfileChangeRequest $item): array => [
                    'id'=>$item->id,'type'=>$item->request_type,'status'=>$item->status,
                    'user'=>['id'=>$item->user->id,'name'=>$item->user->name,'username'=>$item->user->username,'email'=>$item->user->email,'phone'=>$item->user->phone,'tier'=>$item->user->tier],
                    'changes'=>$item->requested_changes,'reason'=>$item->reason,
                    'businessEntityType'=>$item->business_entity_type,
                    'businessRegisteredName'=>$item->business_registered_name,
                    'businessRegistrationNumber'=>$item->business_registration_number,
                    'businessTaxId'=>$item->business_tax_id,
                    'businessRegisteredAddress'=>$item->business_registered_address,
                    'businessState'=>$item->business_state,'businessCountry'=>$item->business_country,
                    'documents'=>$item->business_documents ?? [],
                    'documentCount'=>count($item->business_documents ?? []),
                    'adminNote'=>$item->admin_note,'reviewedAt'=>$item->reviewed_at?->toISOString(),
                    'createdAt'=>$item->created_at?->toISOString(),
                ]),
        ]);
    }

    public function document(ProfileChangeRequest $profileChangeRequest, int $index): BinaryFileResponse
    {
        abort_unless($profileChangeRequest->business_documents && isset($profileChangeRequest->business_documents[$index]), 404);
        $path = $profileChangeRequest->business_documents[$index];
        abort_unless(Storage::disk('local')->exists($path), 404);
        return response()->download(Storage::disk('local')->path($path), 'cac-document-'.$index);
    }

    public function review(Request $request, ProfileChangeRequest $profileChangeRequest, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'decision'=>['required',Rule::in(['approved','rejected'])],
            'admin_note'=>['nullable','string','max:2000'],
        ]);

        if ($profileChangeRequest->status !== 'pending') {
            return back()->with('error','This request has already been reviewed.');
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($profileChangeRequest,$data,$request): void {
                $locked = ProfileChangeRequest::query()->lockForUpdate()->findOrFail($profileChangeRequest->id);
                if ($locked->status !== 'pending') throw new \RuntimeException('This request has already been reviewed.');

                if ($data['decision'] === 'approved') {
                    $user = \App\Models\User::query()->lockForUpdate()->findOrFail($locked->user_id);
                    if ($locked->request_type === 'personal') {
                        $changes = $locked->requested_changes ?? [];
                        if (isset($changes['email'])) {
                            $changes['email'] = strtolower(trim($changes['email']));
                            if (\App\Models\User::query()->where('email',$changes['email'])->whereKeyNot($user->id)->exists()) throw new \RuntimeException('That email address is already in use.');
                            $changes['email_verified_at'] = null;
                        }
                        if (array_key_exists('phone',$changes)) {
                            $changes['phone'] = preg_replace('/[^0-9+]/','',(string)$changes['phone']);
                            $changes['phone_verified_at'] = null;
                        }
                        $user->forceFill($changes)->saveOrFail();
                    } else {
                        $user->forceFill([
                            'name'=>$locked->business_registered_name,
                            'account_type'=>((int)$user->tier===5?'api':'merchant'),
                            'business_name'=>$locked->business_registered_name,
                            'business_registration_number'=>$locked->business_registration_number,
                            'business_type'=>$locked->business_entity_type,
                            'business_address'=>$locked->business_registered_address,
                            'business_state'=>$locked->business_state,
                            'business_country'=>$locked->business_country ?: 'Nigeria',
                            'merchant_verified_at'=>now(),
                            'tier_upgrade_status'=>in_array((int)$user->tier,[4,5],true) ? 'approved' : $user->tier_upgrade_status,
                        ])->saveOrFail();
                    }
                }

                $locked->forceFill([
                    'status'=>$data['decision'],
                    'admin_note'=>trim((string)($data['admin_note'] ?? '')) ?: null,
                    'reviewed_by'=>$request->user()->id,
                    'reviewed_at'=>now(),
                ])->saveOrFail();
            });

            try {
                $audit->record('profile.change.reviewed',$profileChangeRequest->fresh(),[
                    'request_id'=>$profileChangeRequest->id,'decision'=>$data['decision'],
                    'request_type'=>$profileChangeRequest->request_type,'target_user_id'=>$profileChangeRequest->user_id,
                ],$request);
            } catch (\Throwable $e) { report($e); }

            return back()->with('success','Profile change request '.$data['decision'].'.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error',$e->getMessage() ?: 'Profile change review failed safely.');
        }
    }
}
