<?php

namespace App\Http\Controllers;

use App\Models\CacOrder;
use App\Models\CacOrderDocument;
use App\Models\CacServiceProduct;
use App\Services\Cac\CacDocumentService;
use App\Services\Cac\CacOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CacController extends Controller
{
    public function index()
    {
        return Inertia::render('Cac/Index', [
            'products' => CacServiceProduct::where('enabled', true)
                ->orderBy('name')
                ->get(['id', 'identifier', 'name', 'service_type', 'description', 'currency', 'selling_price_minor', 'requirements']),
        ]);
    }

    public function store(Request $request, CacOrderService $orders)
    {
        $data = $request->validate([
            'service_product_id' => ['required', 'integer', 'exists:cac_service_products,id'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'company_type' => ['nullable', 'string', 'max:100'],
            'payload' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ]);

        $product = CacServiceProduct::where('enabled', true)->findOrFail($data['service_product_id']);
        $payload = array_merge(
            (array) ($data['payload'] ?? []),
            array_filter([
                'customer_name' => $data['customer_name'] ?? null,
                'business_name' => $data['business_name'] ?? null,
                'company_type' => $data['company_type'] ?? null,
            ], fn ($v) => $v !== null)
        );

        $key = $data['idempotency_key'] ?? hash('sha256', $request->user()->id . '|' . $product->id . '|' . $request->session()->getId() . '|' . (string) $request->header('X-Idempotency-Key', ''));
        $order = $orders->create((int) $request->user()->id, $product, $payload, $key);

        return redirect()->route('cac.orders.show', $order)
            ->with('success', 'CAC application created. Complete the required documents for review.');
    }

    public function orders(Request $request)
    {
        return Inertia::render('Cac/Orders', [
            'orders' => CacOrder::where('user_id', $request->user()->id)
                ->with('product')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function show(Request $request, CacOrder $order)
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);

        return Inertia::render('Cac/Order', [
            'order' => $order->load(['product', 'documents', 'statusHistory']),
        ]);
    }

    public function uploadDocument(Request $request, CacOrder $order, CacDocumentService $documents)
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);

        $data = $request->validate([
            'document_type' => ['required', 'string', 'max:80'],
            'document' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $documents->upload(
            $order,
            $data['document'],
            $data['document_type'],
            (int) $request->user()->id
        );

        return back()->with('success', 'CAC document uploaded.');
    }

    public function deleteDocument(Request $request, CacOrder $order, int $document, CacDocumentService $documents)
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);
        $record = $order->documents()->findOrFail($document);
        $documents->delete($record, (int) $request->user()->id);

        return back()->with('success', 'CAC document deleted.');
    }

    public function downloadDocument(Request $request, CacOrder $order, int $document)
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);
        $record = $order->documents()->findOrFail($document);
        $disk = Storage::disk($record->storage_disk);

        abort_unless($disk->exists($record->storage_path), 404);

        return $disk->download($record->storage_path, $record->original_name, [
            'Content-Type' => $record->mime_type,
            'Content-Disposition' => 'attachment; filename="' . addslashes($record->original_name) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
