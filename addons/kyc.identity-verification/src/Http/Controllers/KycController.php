<?php

namespace Semizzy\Addons\Kyc\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Semizzy\Addons\Kyc\Models\KycApplication;

class KycController extends Controller
{
    public function index(Request $request): Response
    {
        $application = KycApplication::with('documents')
            ->where('user_id', $request->user()->id)
            ->latest('id')->first();

        return Inertia::render('Kyc', [
            'kyc' => $application ? [
                'id' => $application->id,
                'status' => $application->status,
                'identityType' => $application->identity_type,
                'submittedAt' => $application->submitted_at?->toISOString(),
                'reviewedAt' => $application->reviewed_at?->toISOString(),
                'rejectionReason' => $application->rejection_reason,
                'hasDocument' => $application->documents->isNotEmpty(),
            ] : [
                'status' => 'not_started',
                'identityType' => null,
                'submittedAt' => null,
                'reviewedAt' => null,
                'rejectionReason' => null,
                'hasDocument' => false,
            ],
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $user = $request->user();
        $existing = KycApplication::where('user_id', $user->id)->where('status', 'approved')->exists();
        if ($existing) {
            return back()->with('success', 'Your KYC is already approved.');
        }

        $data = $request->validate([
            'identity_type' => ['required', Rule::in(['nin', 'bvn', 'passport', 'drivers_license'])],
            'identity_number' => ['required', 'string', 'min:4', 'max:120'],
            'identity_document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:10240'],
        ]);

        $pending = KycApplication::where('user_id', $user->id)->where('status', 'pending')->first();
        if ($pending) {
            return back()->withErrors(['identity_type' => 'You already have a KYC application awaiting review.']);
        }

        $application = KycApplication::create([
            'user_id' => $user->id,
            'identity_type' => $data['identity_type'],
            'identity_number' => trim($data['identity_number']),
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $file = $request->file('identity_document');
        $path = $file->store('kyc/documents', 'local');
        $application->documents()->create([
            'document_type' => $data['identity_type'],
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'status' => 'submitted',
        ]);

        return back()->with('success', 'KYC application submitted for review.');
    }

    public function document(Request $request, int $document): mixed
    {
        $doc = KycApplication::where('user_id', $request->user()->id)
            ->whereHas('documents', fn ($q) => $q->whereKey($document))
            ->firstOrFail()->documents()->whereKey($document)->firstOrFail();

        abort_unless(Storage::disk($doc->disk)->exists($doc->path), 404);
        return response()->download(Storage::disk($doc->disk)->path($doc->path), $doc->original_name ?: 'kyc-document');
    }
}
