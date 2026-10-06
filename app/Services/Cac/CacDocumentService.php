<?php

namespace App\Services\Cac;

use App\Models\CacOrder;
use App\Models\CacOrderDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CacDocumentService
{
    private const MAX_BYTES = 10 * 1024 * 1024;
    private const MIME = ['application/pdf', 'image/jpeg', 'image/png'];

    public function upload(CacOrder $order, UploadedFile $file, string $documentType, int $userId): CacOrderDocument
    {
        if ((int) $order->user_id !== $userId) {
            abort(404);
        }

        if (!in_array($order->status, ['pending_review', 'documents_required'], true)) {
            throw ValidationException::withMessages(['order' => 'Documents can only be uploaded while the CAC order is under review.']);
        }

        $documentType = Str::lower(trim($documentType));
        if ($documentType === '' || !preg_match('/^[a-z0-9._-]{1,80}$/', $documentType)) {
            throw ValidationException::withMessages(['document_type' => 'Invalid document type.']);
        }

        if (!$file->isValid() || ($file->getSize() ?? 0) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['document' => 'Document must not exceed 10 MB and must be a valid upload.']);
        }

        $mime = (string) $file->getMimeType();
        if (!in_array($mime, self::MIME, true)) {
            throw ValidationException::withMessages(['document' => 'Only PDF, JPEG and PNG documents are accepted.']);
        }

        $realPath = $file->getRealPath();
        $checksum = $realPath && is_file($realPath) ? hash_file('sha256', $realPath) : false;
        if (!is_string($checksum) || $checksum === '') {
            throw new \RuntimeException('Unable to calculate document checksum.');
        }

        $disk = 'local';
        $path = $file->store('private/cac/' . $order->uuid, $disk);
        if (!$path) {
            throw new \RuntimeException('Document storage failed.');
        }

        try {
            return DB::transaction(function () use ($order, $file, $documentType, $disk, $path, $mime, $checksum) {
                return CacOrderDocument::create([
                    'cac_order_id' => $order->id,
                    'document_type' => $documentType,
                    'storage_disk' => $disk,
                    'storage_path' => $path,
                    'original_name' => Str::limit(Str::of($file->getClientOriginalName())->replace(['/', '\\\\'], '_')->toString(), 255, ''),
                    'mime_type' => $mime,
                    'size_bytes' => (int) $file->getSize(),
                    'status' => 'uploaded',
                    'checksum' => $checksum,
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }
    }

    public function delete(CacOrderDocument $document, int $userId): void
    {
        if ((int) $document->order->user_id !== $userId) {
            abort(404);
        }

        if (!in_array($document->order->status, ['pending_review', 'documents_required'], true)) {
            throw ValidationException::withMessages(['order' => 'Documents can no longer be deleted for this CAC order.']);
        }

        Storage::disk($document->storage_disk)->delete($document->storage_path);
        $document->delete();
    }

    public function requiredTypes(CacOrder $order): array
    {
        $requirements = (array) ($order->product?->requirements ?? []);
        $documents = $requirements['documents'] ?? [];

        if (!is_array($documents)) {
            return [];
        }

        $types = [];
        foreach ($documents as $key => $value) {
            if (is_string($value)) {
                $types[] = Str::lower(trim($value));
                continue;
            }

            if (is_string($key) && is_array($value) && ($value['required'] ?? true)) {
                $type = $value['type'] ?? $key;
                if (is_string($type) && trim($type) !== '') {
                    $types[] = Str::lower(trim($type));
                }
            }
        }

        return array_values(array_unique(array_filter($types)));
    }

    public function missingRequiredTypes(CacOrder $order): array
    {
        $required = $this->requiredTypes($order);
        if ($required === []) {
            return [];
        }

        $accepted = $order->documents()
            ->where('status', 'accepted')
            ->pluck('document_type')
            ->map(fn ($type) => Str::lower(trim((string) $type)))
            ->all();

        return array_values(array_diff($required, $accepted));
    }
}
