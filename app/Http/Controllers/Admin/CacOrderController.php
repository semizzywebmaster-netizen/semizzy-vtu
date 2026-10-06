<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CacOrder;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CacOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = CacOrder::query()->withCount('documents')->with('product')->latest();
        if ($request->filled('status')) $query->where('status', $request->string('status')->toString());
        if ($request->filled('service_type')) $query->where('service_type', $request->string('service_type')->toString());
        return Inertia::render('Admin/Cac/Orders', [
            'orders' => $query->paginate(25)->withQueryString(),
            'filters' => $request->only(['status','service_type']),
        ]);
    }

    public function show(CacOrder $order)
    {
        return Inertia::render('Admin/Cac/Order', [
            'order' => $order->load(['product','documents','attempts']),
        ]);
    }

    public function documentReview(Request $request, CacOrder $order, int $document, AuditLogger $audit)
    {
        $data = $request->validate(['status' => ['required','in:accepted,rejected'], 'note' => ['nullable','string','max:2000']]);
        $record = $order->documents()->findOrFail($document);
        $record->update(['status' => $data['status']]);
        $audit->record('cac.document.reviewed', $record, ['order_id'=>$order->id,'status'=>$data['status'],'note'=>$data['note'] ?? null], $request);
        return back()->with('success', 'CAC document review updated.');
    }

    public function review(Request $request, CacOrder $order, AuditLogger $audit, \App\Services\Cac\CacDocumentService $documents)
    {
        $data = $request->validate([
            'action' => ['required','in:approve,reject,request_documents'],
            'note' => ['nullable','string','max:2000'],
        ]);
        $allowed = [
            'pending_review' => ['approve' => 'provider_ready', 'reject' => 'rejected', 'request_documents' => 'documents_required'],
            'documents_required' => ['approve' => 'provider_ready', 'reject' => 'rejected', 'request_documents' => 'documents_required'],
        ];
        $status = $allowed[$order->status][$data['action']] ?? null;
        if (!$status) {
            return back()->withErrors(['action' => 'This CAC order cannot be reviewed from its current status.']);
        }
        if ($data['action'] === 'approve' && $documents->missingRequiredTypes($order->load('product'))) {
            return back()->withErrors(['documents' => 'All required CAC documents must be uploaded before approval.']);
        }
        $order->update([
            'status' => $status,
            'failure_message' => $data['action'] === 'reject' ? ($data['note'] ?? 'CAC order rejected during review.') : null,
        ]);
        $audit->record('cac.order.reviewed', $order, [
            'action' => $data['action'],
            'status' => $status,
            'note' => $data['note'] ?? null,
        ], $request);
        return back()->with('success', 'CAC order review status updated.');
    }
}
