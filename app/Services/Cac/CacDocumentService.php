<?php

namespace App\Services\Cac;

use App\Models\CacOrder;
use App\Models\CacOrderDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class CacDocumentService
{
    private const MAX_BYTES = 10 * 1024 * 1024;
    private const MIME = ['application/pdf','image/jpeg','image/png'];

    public function upload(CacOrder $order, UploadedFile $file, string $documentType, int $userId): CacOrderDocument
    {
        if ((int) $order->user_id !== $userId) abort(404);
        if ($order->status !== 'pending_review') {
            throw ValidationException::withMessages(['order' => 'Documents can only be uploaded while the CAC order is under review.']);
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages(['document' => 'Document must not exceed 10 MB.']);
        }
        if (!in_array($file->getMimeType(), self::MIME, true)) {
            throw ValidationException::withMessages(['document' => 'Only PDF, JPEG and PNG documents are accepted.']);
        }

        $disk = 'local';
        $path = $file->store('private/cac/'. $order->uuid, $disk);
        if (!$path) throw new \RuntimeException('Document storage failed.');

        try {
            return DB::transaction(function () use ($order, $file, $documentType, $disk, $path) {
                return CacOrderDocument::create([
                    'cac_order_id' => $order->id,
                    'document_type' => Str::lower(trim($documentType)),
                    'storage_disk' => $disk,
                    'storage_path' => $path,
                    'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                    'mime_type' => $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                    'status' => 'uploaded',
                    'checksum' => hash_file('sha256', $file->getRealPath()),
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }
    }

    public function delete(CacOrderDocument $document, int $userId): void
    {
        if ((int) $document->order->user_id !== $userId) abort(404);
        Storage::disk($document->storage_disk)->delete($document->storage_path);
        $document->delete();
    }
}
