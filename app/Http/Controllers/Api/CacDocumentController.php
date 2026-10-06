<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CacOrder;
use App\Services\Cac\CacDocumentService;
use Illuminate\Http\Request;

class CacDocumentController extends Controller
{
    public function store(Request $request, CacOrder $order, CacDocumentService $documents)
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);

        $data = $request->validate([
            'document_type' => ['required','string','max:100','regex:/^[a-z0-9._-]+$/i'],
            'document' => ['required','file','mimetypes:application/pdf,image/jpeg,image/png','max:10240'],
        ]);

        $document = $documents->upload($order, $data['document'], $data['document_type'], (int) $request->user()->id);

        return response()->json(['data' => $document], 201);
    }

    public function destroy(Request $request, CacOrder $order, int $document, CacDocumentService $documents)
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);
        $record = $order->documents()->findOrFail($document);
        $documents->delete($record, (int) $request->user()->id);

        return response()->json(['message' => 'Document removed.']);
    }
}
