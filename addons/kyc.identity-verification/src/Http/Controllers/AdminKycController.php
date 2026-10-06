<?php

namespace Semizzy\Addons\Kyc\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Semizzy\Addons\Kyc\Models\KycApplication;

class AdminKycController extends Controller
{
    public function index(): Response
    {
        $applications = KycApplication::with('user')
            ->latest('submitted_at')->paginate(25)->through(fn (KycApplication $a) => [
                'id' => $a->id,
                'name' => $a->user->name,
                'username' => $a->user->username,
                'email' => $a->user->email,
                'phone' => $a->user->phone,
                'tier' => (int) $a->user->tier,
                'status' => $a->status,
                'identityType' => $a->identity_type,
                'identityNumber' => $a->identity_number ? '••••••••' . substr((string) $a->identity_number, -4) : null,
                'submittedAt' => $a->submitted_at?->toISOString(),
                'reviewedAt' => $a->reviewed_at?->toISOString(),
                'rejectionReason' => $a->rejection_reason,
                'documents' => $a->documents->map(fn ($d) => [
                    'id' => $d->id,
                    'name' => $d->original_name,
                    'url' => route('admin.kyc.document', $d->id),
                ]),
            ]);

        return Inertia::render('Admin/Kyc', ['applications' => $applications]);
    }

    public function review(Request $request, KycApplication $application, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if (!in_array($application->status, ['pending', 'rejected'], true)) {
            return back()->with('error', 'This KYC application is not awaiting review.');
        }

        DB::transaction(function () use ($application, $data, $request): void {
            $application->forceFill([
                'status' => $data['decision'],
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->id,
                'rejection_reason' => $data['decision'] === 'rejected' ? trim((string) ($data['reason'] ?? '')) : null,
            ])->saveOrFail();

            // KYC approval satisfies the Core Tier 2 identity-verification requirement,
            // but never silently upgrades users beyond Tier 2.
            if ($data['decision'] === 'approved' && (int) $application->user->tier < 2) {
                $application->user->forceFill([
                    'tier' => 2,
                    'tier_upgrade_status' => 'approved',
                ])->saveOrFail();
            }
        });

        try {
            $audit->record('kyc.application.reviewed', $application->fresh(), [
                'application_id' => $application->id,
                'decision' => $data['decision'],
                'reason' => $application->rejection_reason,
            ], $request);
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'KYC application ' . $data['decision'] . '.');
    }

    public function document(int $document): mixed
    {
        $doc = \Semizzy\Addons\Kyc\Models\KycDocument::findOrFail($document);
        abort_unless(Storage::disk($doc->disk)->exists($doc->path), 404);
        return response()->download(Storage::disk($doc->disk)->path($doc->path), $doc->original_name ?: 'kyc-document');
    }
}
