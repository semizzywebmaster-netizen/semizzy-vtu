<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CacOrder;
use App\Models\CacOrderDocument;
use App\Services\Audit\AuditLogger;
use App\Services\Cac\CacDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CacOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = CacOrder::query()->withCount('documents')->with('product')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('service_type')) {
            $query->where('service_type', $request->string('service_type')->toString());
        }

        return Inertia::render('Admin/Cac/Orders', [
            'orders' => $query->paginate(25)->withQueryString(),
            'filters' => $request->only(['status', 'service_type']),
        ]);
    }

    public function show(CacOrder $order)
    {
        return Inertia::render('Admin/Cac/Order', [
            'order' => $order->load(['product', 'documents', 'attempts', 'statusHistory']),
        ]);
    }

    public function documentReview(Request $request, CacOrder $order, int $document, AuditLogger $audit)
    {
        $data = $request->validate([
            'status' => ['required', 'in:accepted,rejected'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $record = $order->documents()->findOrFail($document);
        $record->update([
            'status' => $data['status'],
            'review_note' => $data['note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $audit->record('cac.document.reviewed', $record, [
            'order_id' => $order->id,
            'status' => $data['status'],
            'note' => $data['note'] ?? null,
        ], $request);

        return back()->with('success', 'CAC document review updated.');
    }

    public function downloadDocument(Request $request, CacOrder $order, int $document)
    {
        $record = $order->documents()->findOrFail($document);
        $disk = Storage::disk($record->storage_disk);

        abort_unless($disk->exists($record->storage_path), 404);

        return $disk->download($record->storage_path, $record->original_name, [
            'Content-Type' => $record->mime_type,
            'Content-Disposition' => 'attachment; filename="' . addslashes($record->original_name) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function review(Request $request, CacOrder $order, AuditLogger $audit, CacDocumentService $documents)
    {
        $data = $request->validate([
            'action' => ['required', 'in:approve,reject,request_documents'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $allowed = [
            'pending_review' => [
                'approve' => 'provider_ready',
                'reject' => 'rejected',
                'request_documents' => 'documents_required',
            ],
            'documents_required' => [
                'approve' => 'provider_ready',
                'reject' => 'rejected',
                'request_documents' => 'documents_required',
            ],
        ];

        $status = $allowed[$order->status][$data['action']] ?? null;
        if (!$status) {
            return back()->withErrors(['action' => 'This CAC order cannot be reviewed from its current status.']);
        }

        if ($data['action'] === 'approve' && $documents->missingRequiredTypes($order->load(['product', 'documents']))) {
            return back()->withErrors(['documents' => 'All required CAC documents must be uploaded and accepted before approval.']);
        }

        $from = $order->status;
        $order->update([
            'status' => $status,
            'failure_message' => $data['action'] === 'reject'
                ? ($data['note'] ?? 'CAC order rejected during review.')
                : null,
        ]);

        if ($from !== $status) {
            $order->statusHistory()->create([
                'from_status' => $from,
                'to_status' => $status,
                'source' => 'admin_review',
                'reason' => $data['note'] ?? 'CAC order review action',
                'metadata' => ['action' => $data['action'], 'reviewer_id' => $request->user()->id],
            ]);
        }

        $audit->record('cac.order.reviewed', $order, [
            'action' => $data['action'],
            'status' => $status,
            'note' => $data['note'] ?? null,
        ], $request);

        return back()->with('success', 'CAC order review status updated.');
    }
}
