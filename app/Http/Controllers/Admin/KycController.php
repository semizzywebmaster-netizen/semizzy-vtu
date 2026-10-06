<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class KycController extends Controller
{
    public function index(): Response
    {
        $applications=User::query()->whereIn('kyc_status',['pending','approved','rejected'])->latest('kyc_submitted_at')->paginate(25)->through(fn(User $u)=>[
            'id'=>$u->id,'name'=>$u->name,'username'=>$u->username,'email'=>$u->email,'phone'=>$u->phone,
            'tier'=>(int)$u->tier,'status'=>$u->kyc_status,'identityType'=>$u->identity_type,
            'identityNumber'=>$u->identity_number ? '••••••••'.substr((string)$u->identity_number,-4) : null,
            'documentUrl'=>$u->identity_document_path ? route('admin.users.kyc.document',$u) : null,
            'submittedAt'=>$u->kyc_submitted_at?->toISOString(),'reviewedAt'=>$u->kyc_reviewed_at?->toISOString(),'rejectionReason'=>$u->kyc_rejection_reason,
        ]);
        return Inertia::render('Admin/Kyc',['applications'=>$applications]);
    }

    public function review(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $data=$request->validate(['decision'=>['required','in:approved,rejected'],'reason'=>['nullable','string','max:500']]);
        if(!in_array($user->kyc_status,['pending','rejected'],true)) return back()->with('error','This KYC application is not awaiting review.');
        $user->forceFill(['kyc_status'=>$data['decision'],'kyc_reviewed_at'=>now(),'kyc_rejection_reason'=>$data['decision']==='rejected' ? trim((string)($data['reason']??'')) : null])->saveOrFail();
        try{$audit->record('admin.kyc.reviewed',$user->fresh(),['target_user_id'=>$user->id,'decision'=>$data['decision'],'reason'=>$data['decision']==='rejected'?trim((string)($data['reason']??'')):null],$request);}catch(\Throwable $e){report($e);}
        return back()->with('success','KYC application '.$data['decision'].'.');
    }

    public function document(User $user): mixed
    {
        abort_unless($user->identity_document_path && Storage::disk('local')->exists($user->identity_document_path),404);
        return response()->download(Storage::disk('local')->path($user->identity_document_path),'kyc-'.$user->id.'-document');
    }
}
